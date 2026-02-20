<?php

declare(strict_types=1);

/**
 * Database Migrator
 *
 * database/migrations/ 폴더의 버전별 SQL 파일을 관리하고
 * schema_versions 테이블을 통해 적용 상태를 추적합니다.
 *
 * CLI 실행: php src/helpers/migrator.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

/**
 * schema_versions 테이블이 없으면 생성
 */
function ensureSchemaVersionsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_versions` (
            `version` VARCHAR(10) NOT NULL COMMENT '버전 번호 (V001, V002...)',
            `description` VARCHAR(255) NOT NULL COMMENT '마이그레이션 설명',
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * 현재 DB에 적용된 마이그레이션 버전 목록 조회
 *
 * @return array<string, string> ['V001' => '2026-02-20 ...', ...]
 */
function getAppliedVersions(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT `version`, `applied_at` FROM `schema_versions` ORDER BY `version`");
    $versions = [];
    while ($row = $stmt->fetch()) {
        $versions[$row['version']] = $row['applied_at'];
    }
    return $versions;
}

/**
 * database/migrations/ 폴더에서 마이그레이션 파일 스캔
 *
 * @return array<int, array{version: string, description: string, path: string}>
 */
function scanMigrationFiles(string $migrationsDir): array
{
    if (!is_dir($migrationsDir)) {
        return [];
    }

    $files = glob($migrationsDir . '/V[0-9][0-9][0-9]__*.sql');
    if ($files === false) {
        return [];
    }

    $migrations = [];
    foreach ($files as $file) {
        $filename = basename($file, '.sql');
        // V001__initial_schema → version=V001, description=initial_schema
        if (preg_match('/^(V\d{3})__(.+)$/', $filename, $matches)) {
            $migrations[] = [
                'version' => $matches[1],
                'description' => $matches[2],
                'path' => $file,
            ];
        }
    }

    // 버전 순으로 정렬
    usort($migrations, fn($a, $b) => strcmp($a['version'], $b['version']));

    return $migrations;
}

/**
 * 미적용 마이그레이션 실행
 *
 * @return array{applied: string[], skipped: string[], errors: string[]}
 */
function runMigrations(PDO $pdo, string $migrationsDir): array
{
    $result = [
        'applied' => [],
        'skipped' => [],
        'errors' => [],
    ];

    // 1. schema_versions 테이블 확인/생성
    ensureSchemaVersionsTable($pdo);

    // 2. 적용된 버전 조회
    $appliedVersions = getAppliedVersions($pdo);

    // 3. 마이그레이션 파일 스캔
    $migrations = scanMigrationFiles($migrationsDir);

    if (empty($migrations)) {
        logInfo('마이그레이션 파일 없음', ['dir' => $migrationsDir], 'migrator');
        return $result;
    }

    // 4. 미적용 마이그레이션 실행
    foreach ($migrations as $migration) {
        $version = $migration['version'];

        // 이미 적용된 버전은 스킵
        if (isset($appliedVersions[$version])) {
            $result['skipped'][] = $version;
            continue;
        }

        // SQL 파일 읽기
        $sql = file_get_contents($migration['path']);
        if ($sql === false) {
            $error = "SQL 파일 읽기 실패: {$migration['path']}";
            logError($error, [], 'migrator');
            $result['errors'][] = $error;
            break; // 실패 시 중단
        }

        try {
            // 마이그레이션 실행
            $pdo->exec($sql);

            // schema_versions에 기록
            $stmt = $pdo->prepare(
                "INSERT INTO `schema_versions` (`version`, `description`) VALUES (:version, :description)"
            );
            $stmt->execute([
                'version' => $version,
                'description' => $migration['description'],
            ]);

            $result['applied'][] = $version;
            logInfo("마이그레이션 적용 완료: {$version}", [
                'description' => $migration['description'],
            ], 'migrator');
        } catch (\PDOException $e) {
            $error = "마이그레이션 실패 [{$version}]: {$e->getMessage()}";
            logError($error, [
                'version' => $version,
                'file' => $migration['path'],
            ], 'migrator');
            $result['errors'][] = $error;
            break; // 실패 시 후속 마이그레이션도 중단
        }
    }

    return $result;
}

/**
 * 현재 DB 마이그레이션 상태 조회
 *
 * @return array{current_version: string|null, applied: array, pending: array}
 */
function getMigrationStatus(PDO $pdo, string $migrationsDir): array
{
    ensureSchemaVersionsTable($pdo);

    $appliedVersions = getAppliedVersions($pdo);
    $migrations = scanMigrationFiles($migrationsDir);

    $currentVersion = null;
    if (!empty($appliedVersions)) {
        $keys = array_keys($appliedVersions);
        $currentVersion = end($keys);
    }

    $pending = [];
    foreach ($migrations as $migration) {
        if (!isset($appliedVersions[$migration['version']])) {
            $pending[] = $migration['version'] . ' — ' . $migration['description'];
        }
    }

    return [
        'current_version' => $currentVersion,
        'applied' => $appliedVersions,
        'pending' => $pending,
    ];
}

// ============================================
// CLI 실행 지원
// ============================================
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $migrationsDir = __DIR__ . '/../../database/migrations';

    echo "=== NOTTO Database Migrator ===\n\n";

    try {
        $pdo = getDatabase();
    } catch (\Exception $e) {
        echo "❌ DB 연결 실패: {$e->getMessage()}\n";
        exit(1);
    }

    // --status 옵션: 상태만 조회
    if (in_array('--status', $argv)) {
        $status = getMigrationStatus($pdo, $migrationsDir);
        echo "현재 버전: " . ($status['current_version'] ?? '없음') . "\n";
        echo "적용된 버전:\n";
        foreach ($status['applied'] as $v => $date) {
            echo "  ✅ {$v} ({$date})\n";
        }
        echo "미적용 버전:\n";
        if (empty($status['pending'])) {
            echo "  (없음 — 최신 상태)\n";
        } else {
            foreach ($status['pending'] as $p) {
                echo "  ⏳ {$p}\n";
            }
        }
        exit(0);
    }

    // 마이그레이션 실행
    $result = runMigrations($pdo, $migrationsDir);

    if (!empty($result['applied'])) {
        echo "✅ 적용 완료:\n";
        foreach ($result['applied'] as $v) {
            echo "  - {$v}\n";
        }
    }

    if (!empty($result['skipped'])) {
        echo "⏭️  이미 적용됨:\n";
        foreach ($result['skipped'] as $v) {
            echo "  - {$v}\n";
        }
    }

    if (!empty($result['errors'])) {
        echo "❌ 오류 발생:\n";
        foreach ($result['errors'] as $err) {
            echo "  - {$err}\n";
        }
        exit(1);
    }

    if (empty($result['applied']) && empty($result['errors'])) {
        echo "✅ 모든 마이그레이션이 이미 적용되어 있습니다.\n";
    }

    exit(0);
}
