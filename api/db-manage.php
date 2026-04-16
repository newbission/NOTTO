<?php

declare(strict_types=1);

/**
 * POST /api/db-manage.php — DB 관리 (관리자 전용)
 *
 * Actions:
 *   - reset: 전체 테이블 DROP → schema.sql 재실행 → 현재 회차 자동 생성
 *   - backup: 전체 데이터를 SQL INSERT 문으로 내보내기
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/helpers/RoundHelper.php';

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse(405, 'METHOD_NOT_ALLOWED', '허용되지 않은 요청 방식입니다.');
}
requireAdminToken();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'reset':
        handleReset();
        break;
    case 'backup':
        handleBackup();
        break;
    default:
        errorResponse(400, 'INVALID_ACTION', 'action은 reset 또는 backup만 가능합니다.');
}

/**
 * DB 초기화: 전체 테이블 DROP → schema.sql 재실행
 */
function handleReset(): void
{
    logInfo('DB 초기화 시작', [], 'db');

    $pdo = getDatabase();

    // 외래키 체크 임시 비활성화
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // 모든 테이블 조회 + DROP
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $droppedCount = 0;
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        $droppedCount++;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    logInfo('기존 테이블 삭제 완료', ['count' => $droppedCount, 'tables' => $tables], 'db');

    // schema.sql 재실행
    $schemaPath = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaPath)) {
        logError('schema.sql 파일 없음', ['path' => $schemaPath], 'db');
        errorResponse(500, 'SCHEMA_NOT_FOUND', 'schema.sql 파일을 찾을 수 없습니다.');
    }

    $sql = file_get_contents($schemaPath);
    $pdo->exec($sql);
    logInfo('schema.sql 적용 완료', [], 'db');

    // 현재 회차 자동 생성
    $round = RoundHelper::ensureCurrentRound();
    logInfo('DB 초기화 완료', ['round' => $round], 'db');

    jsonResponse([
        'dropped_tables' => $tables,
        'dropped_count' => $droppedCount,
        'current_round' => $round,
        'message' => "DB 초기화 완료. {$droppedCount}개 테이블 삭제 후 재생성. 현재 회차: {$round['round_number']}회",
    ]);
}

/**
 * DB 백업: 전체 데이터를 SQL INSERT 문으로 내보내기
 */
function handleBackup(): void
{
    logInfo('DB 백업 시작', [], 'db');

    $pdo = getDatabase();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $output = "-- NOTTO DB Backup\n";
    $output .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Tables: " . count($tables) . "\n";
    $output .= "SET NAMES utf8mb4;\n\n";

    $totalRows = 0;

    foreach ($tables as $table) {
        // 테이블 구조
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        $output .= "-- Table: {$table}\n";
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $createStmt['Create Table'] . ";\n\n";

        // 데이터
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            $output .= "-- (empty)\n\n";
            continue;
        }

        $totalRows += count($rows);
        $columns = array_keys($rows[0]);
        $columnList = implode('`, `', $columns);

        foreach ($rows as $row) {
            $values = array_map(function ($val) use ($pdo) {
                if ($val === null) return 'NULL';
                return $pdo->quote((string) $val);
            }, array_values($row));
            $valueList = implode(', ', $values);
            $output .= "INSERT INTO `{$table}` (`{$columnList}`) VALUES ({$valueList});\n";
        }
        $output .= "\n";
    }

    logInfo('DB 백업 완료', ['tables' => count($tables), 'rows' => $totalRows], 'db');

    // SQL 파일로 다운로드
    $filename = 'notto_backup_' . date('Y-m-d_His') . '.sql';
    header('Content-Type: application/sql');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Content-Length: ' . strlen($output));
    echo $output;
    exit;
}
