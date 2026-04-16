<?php

declare(strict_types=1);

/**
 * GET/POST /api/db-browse.php — DB 브라우저 (관리자 전용)
 *
 * Actions:
 *   - tables: 테이블 목록 + 행 수
 *   - query: 특정 테이블 데이터 조회 (페이징)
 *   - update: 행 업데이트
 *   - delete: 행 삭제
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

requireAdminToken();

$action = $_REQUEST['action'] ?? 'tables';
$pdo = getDatabase();

switch ($action) {
    case 'tables':
        handleTables($pdo);
        break;
    case 'query':
        handleQuery($pdo);
        break;
    case 'update':
        handleUpdate($pdo);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    default:
        errorResponse(400, 'INVALID_ACTION', 'action은 tables, query, update, delete만 가능합니다.');
}

function handleTables(PDO $pdo): void
{
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        $result[] = ['name' => $table, 'rows' => (int) $count];
    }
    jsonResponse($result);
}

function handleQuery(PDO $pdo): void
{
    $table = $_GET['table'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int) ($_GET['limit'] ?? 30)));
    $offset = ($page - 1) * $limit;

    if (!$table || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        errorResponse(400, 'INVALID_TABLE', '유효하지 않은 테이블명입니다.');
    }

    // 테이블 존재 확인
    $exists = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
    if (!$exists) {
        errorResponse(404, 'TABLE_NOT_FOUND', "테이블 '{$table}'을 찾을 수 없습니다.");
    }

    // 컬럼 정보
    $columns = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

    // 총 행 수
    $totalRows = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();

    // 데이터 조회
    $rows = $pdo->query("SELECT * FROM `{$table}` ORDER BY 1 DESC LIMIT {$limit} OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

    // PK 컬럼 찾기
    $pkColumns = array_filter($columns, fn($c) => $c['Key'] === 'PRI');
    $pkColumn = !empty($pkColumns) ? reset($pkColumns)['Field'] : null;

    jsonResponse([
        'table' => $table,
        'columns' => array_map(fn($c) => [
            'name' => $c['Field'],
            'type' => $c['Type'],
            'nullable' => $c['Null'] === 'YES',
            'key' => $c['Key'],
            'default' => $c['Default'],
        ], $columns),
        'pk' => $pkColumn,
        'rows' => $rows,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_rows' => $totalRows,
            'total_pages' => (int) ceil($totalRows / $limit),
        ],
    ]);
}

function handleUpdate(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse(405, 'METHOD_NOT_ALLOWED', 'POST만 허용됩니다.');
    }

    $table = $_POST['table'] ?? '';
    $pk = $_POST['pk'] ?? '';
    $pkValue = $_POST['pk_value'] ?? '';
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? null;

    if (!$table || !$pk || $pkValue === '' || !$field) {
        errorResponse(400, 'MISSING_PARAMS', '필수 파라미터가 누락되었습니다.');
    }

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $pk) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $field)) {
        errorResponse(400, 'INVALID_NAME', '유효하지 않은 테이블/컬럼명입니다.');
    }

    $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$field}` = ? WHERE `{$pk}` = ?");
    $stmt->execute([$value, $pkValue]);

    logInfo('DB 브라우저 — 행 수정', [
        'table' => $table,
        'pk' => $pk,
        'pk_value' => $pkValue,
        'field' => $field,
        'value' => $value,
    ], 'db');

    jsonResponse(['updated' => $stmt->rowCount(), 'message' => '수정 완료']);
}

function handleDelete(PDO $pdo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse(405, 'METHOD_NOT_ALLOWED', 'POST만 허용됩니다.');
    }

    $table = $_POST['table'] ?? '';
    $pk = $_POST['pk'] ?? '';
    $pkValue = $_POST['pk_value'] ?? '';

    if (!$table || !$pk || $pkValue === '') {
        errorResponse(400, 'MISSING_PARAMS', '필수 파라미터가 누락되었습니다.');
    }

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $pk)) {
        errorResponse(400, 'INVALID_NAME', '유효하지 않은 테이블/컬럼명입니다.');
    }

    $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$pk}` = ?");
    $stmt->execute([$pkValue]);

    logInfo('DB 브라우저 — 행 삭제', [
        'table' => $table,
        'pk' => $pk,
        'pk_value' => $pkValue,
    ], 'db');

    jsonResponse(['deleted' => $stmt->rowCount(), 'message' => '삭제 완료']);
}
