<?php

declare(strict_types=1);

/**
 * Migration API Endpoint
 *
 * POST /api/migrate.php
 * Header: Authorization: Bearer {ADMIN_TOKEN}
 *
 * 미적용 마이그레이션을 실행하고 결과를 반환합니다.
 * ?status 파라미터로 현재 상태만 조회할 수 있습니다.
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/migrator.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

// CORS & Method 처리
header('Content-Type: application/json; charset=utf-8');

// Admin 토큰 인증 (Apache는 Authorization 헤더를 제거할 수 있음)
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

// apache_request_headers() 폴백
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $token = $matches[1];
} elseif (isset($_GET['token'])) {
    $token = $_GET['token']; // 브라우저 테스트용 토큰
}

$adminToken = env('ADMIN_TOKEN', '');
if (empty($adminToken) || $token !== $adminToken) {
    logWarn('마이그레이션 API 인증 실패', ['token' => substr($token, 0, 5) . '...'], 'api');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '인증 실패'], JSON_UNESCAPED_UNICODE);
    exit;
}

$migrationsDir = __DIR__ . '/../database/migrations';

try {
    $pdo = getDatabase();

    // 실행 모드 확인 (POST 요청이거나 GET 요청에 run=1 파라미터가 있을 때)
    $isRunMode = $_SERVER['REQUEST_METHOD'] === 'POST' || !empty($_GET['run']);

    // 상태 조회
    if (!$isRunMode) {
        $status = getMigrationStatus($pdo, $migrationsDir);
        logInfo('마이그레이션 상태 조회', $status, 'api');
        echo json_encode([
            'success' => true,
            'data' => $status,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 마이그레이션 실행
    logInfo('마이그레이션 실행 시작', [], 'api');
    $result = runMigrations($pdo, $migrationsDir);

    $hasErrors = !empty($result['errors']);
    $statusCode = $hasErrors ? 500 : 200;

    http_response_code($statusCode);
    echo json_encode([
        'success' => !$hasErrors,
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (\Exception $e) {
    logError('마이그레이션 API 오류', ['error' => $e->getMessage()], 'api');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '마이그레이션 실행 중 오류 발생',
    ], JSON_UNESCAPED_UNICODE);
}
