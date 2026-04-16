<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/auth.php?token=... — 토큰 유효성 확인
if ($method === 'GET') {
    $token = $_GET['token'] ?? '';
    $valid = !empty($token) && hash_equals(generateAdminSessionToken(), $token);
    echo json_encode(['valid' => $valid]);
    exit;
}

// POST /api/auth.php { key } — 키 검증 후 세션 토큰 발급
if ($method === 'POST') {
    $submittedKey = trim($_POST['key'] ?? '');
    $adminKey = env('ADMIN_KEY', '');

    if (empty($adminKey)) {
        logError('ADMIN_KEY 환경변수 미설정', [], 'security');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'SERVER_CONFIG_ERROR']);
        exit;
    }

    if (empty($submittedKey) || !hash_equals($adminKey, $submittedKey)) {
        logWarn('관리자 키 인증 실패', ['ip' => $_SERVER['REMOTE_ADDR'] ?? ''], 'security');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'INVALID_KEY']);
        exit;
    }

    logInfo('관리자 로그인 성공', ['ip' => $_SERVER['REMOTE_ADDR'] ?? ''], 'security');
    $token = generateAdminSessionToken();
    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'METHOD_NOT_ALLOWED']);
