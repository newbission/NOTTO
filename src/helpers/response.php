<?php

declare(strict_types=1);

/**
 * JSON Response Helpers
 *
 * 모든 API에서 사용하는 일관된 JSON 응답 포맷
 */

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
 * 관리자 토큰 검증
 */
function requireAdminToken(): void
{
    $token = $_GET['token'] ?? $_POST['token'] ?? '';

    if (empty($token) || $token !== env('ADMIN_TOKEN')) {
        errorResponse(401, 'INVALID_TOKEN', '유효하지 않은 관리자 토큰입니다.');
    }
}
