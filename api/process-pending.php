<?php

declare(strict_types=1);

/**
 * POST /api/process-pending.php — 대기열 처리 (🔒 관리자/크론)
 *
 * pending 이름에 고유번호 생성 → active 전환
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/services/DrawService.php';

requireMethod('POST');
requireAdminToken();

logInfo('대기열 처리 API 호출', ['ip' => $_SERVER['REMOTE_ADDR'] ?? ''], 'api');

$service = new DrawService();
$result = $service->processPending();

if (isset($result['error'])) {
    errorResponse(400, $result['error'], $result['message']);
}

logInfo('대기열 처리 API 완료', $result, 'api');
jsonResponse($result);
