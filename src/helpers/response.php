<?php

declare(strict_types=1);

/**
 * JSON Response Helpers
 *
 * 모든 API에서 사용하는 일관된 JSON 응답 포맷
 */

require_once __DIR__ . '/logger.php';

/**
 * 성공 응답
 */
function jsonResponse(mixed $data, array $meta = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => true,
        'data' => $data,
    ];

    if (!empty($meta)) {
        $response['meta'] = $meta;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 에러 응답
 */
function errorResponse(int $httpCode, string $code, string $message): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');

    logWarn("API 에러 응답", [
        'http_code' => $httpCode,
        'error_code' => $code,
        'message' => $message,
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ], 'api');

    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 메서드 검증 (허용되지 않은 메서드 시 405 반환)
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        errorResponse(405, 'METHOD_NOT_ALLOWED', '허용되지 않은 요청 방식입니다.');
    }
}

/**
 * 오늘의 세션 토큰 생성 (ADMIN_KEY + 날짜 기반, 자정 만료)
 */
function generateAdminSessionToken(): string
{
    $key = env('ADMIN_KEY', '');
    $period = (string) floor(time() / (86400 * 30)); // 30일마다 갱신
    return hash_hmac('sha256', $period, $key);
}

/**
 * 관리자 세션 토큰 검증
 */
function requireAdminToken(): void
{
    $token = $_GET['token'] ?? $_POST['token'] ?? '';

    if (empty($token) || !hash_equals(generateAdminSessionToken(), $token)) {
        logError('관리자 인증 실패', [
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ], 'security');
        errorResponse(401, 'INVALID_TOKEN', '유효하지 않은 토큰입니다.');
    }
}
