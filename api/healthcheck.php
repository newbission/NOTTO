<?php

declare(strict_types=1);

/**
 * GET /api/healthcheck.php — 서버 진단 엔드포인트
 *
 * ?token=ADMIN_TOKEN
 *
 * InfinityFree 등 터미널 접속 불가 환경에서
 * DB 연결, PHP 버전, 파일 권한 등을 원격으로 진단합니다.
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

requireMethod('GET');
requireAdminToken();

$checks = [];

// 1. PHP 버전
$checks['php_version'] = PHP_VERSION;
$checks['php_version_ok'] = version_compare(PHP_VERSION, '8.0.0', '>=');

// 2. 필수 PHP 확장
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
$checks['extensions'] = [];
foreach ($requiredExtensions as $ext) {
    $checks['extensions'][$ext] = extension_loaded($ext);
}

// 3. DB 연결
$checks['db_connected'] = false;
$checks['db_error'] = null;
try {
    $pdo = getDatabase();
    $checks['db_connected'] = true;

    // DB 버전
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    $checks['db_version'] = $version;

    // names 테이블 존재 확인
    $stmt = $pdo->query("SHOW TABLES LIKE 'names'");
    $checks['table_names_exists'] = $stmt->rowCount() > 0;

    // rounds 테이블 존재 확인
    $stmt = $pdo->query("SHOW TABLES LIKE 'rounds'");
    $checks['table_rounds_exists'] = $stmt->rowCount() > 0;

    // 이름 개수
    if ($checks['table_names_exists']) {
        $count = $pdo->query("SELECT COUNT(*) FROM names")->fetchColumn();
        $checks['names_count'] = (int) $count;
    }
} catch (Throwable $e) {
    $checks['db_error'] = $e->getMessage();
}

// 4. logs 디렉토리 쓰기 권한
$logDir = __DIR__ . '/../logs';
$checks['logs_dir_exists'] = is_dir($logDir);
$checks['logs_dir_writable'] = is_writable($logDir);

// 쓰기 테스트
if ($checks['logs_dir_writable']) {
    $testFile = $logDir . '/_healthcheck_test.tmp';
    $written = @file_put_contents($testFile, 'test');
    $checks['logs_write_test'] = $written !== false;
    @unlink($testFile);
} else {
    $checks['logs_write_test'] = false;
}

// 5. .env 로드 확인
$checks['env_loaded'] = [
    'DB_HOST' => !empty(env('DB_HOST')),
    'DB_NAME' => !empty(env('DB_NAME')),
    'APP_ENV' => env('APP_ENV', '(not set)'),
    'APP_DEBUG' => env('APP_DEBUG', '(not set)'),
    'DIRECT_REGISTER' => env('DIRECT_REGISTER', '(not set)'),
];

// 6. 서버 정보
$checks['server'] = [
    'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'timezone' => date_default_timezone_get(),
    'current_time' => date('Y-m-d H:i:s'),
];

// 전체 상태 판정
$checks['overall_ok'] = $checks['php_version_ok']
    && $checks['db_connected']
    && $checks['logs_dir_writable'];

logInfo('헬스체크 수행', ['overall_ok' => $checks['overall_ok']], 'api');

jsonResponse($checks);
