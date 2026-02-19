<?php

declare(strict_types=1);

/**
 * POST /api/draw.php — 매주 번호 생성 (🔒 관리자/크론)
 *
 * Body: round_number=1160&draw_date=2026-02-22
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/services/DrawService.php';

requireMethod('POST');
requireAdminToken();

$roundNumber = (int) ($_POST['round_number'] ?? 0);
$drawDate = $_POST['draw_date'] ?? '';

logInfo('매주 번호 생성 API 호출', ['round_number' => $roundNumber, 'draw_date' => $drawDate], 'api');

if ($roundNumber <= 0) {
    errorResponse(400, 'INVALID_ROUND', '유효한 회차 번호를 입력해주세요.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $drawDate)) {
    errorResponse(400, 'INVALID_DATE', '날짜 형식은 YYYY-MM-DD여야 합니다.');
}

$service = new DrawService();
$result = $service->drawWeekly($roundNumber, $drawDate);

if (isset($result['error'])) {
    $httpCode = match ($result['error']) {
        'ROUND_ALREADY_EXISTS' => 400,
        'NO_ACTIVE_PROMPT' => 400,
        default => 500,
    };
    errorResponse($httpCode, $result['error'], $result['message']);
}

logInfo('매주 번호 생성 완료', $result, 'api');
jsonResponse($result);
