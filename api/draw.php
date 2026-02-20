<?php

declare(strict_types=1);

/**
 * POST /api/draw.php — 매주 번호 생성 (🔒 관리자/크론)
 *
 * Body (선택): round_number=1212&draw_date=2026-02-21
 * 미입력 시 DB에서 다음 회차를 자동 계산
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/helpers/RoundHelper.php';
require_once __DIR__ . '/../src/services/DrawService.php';

requireMethod('POST');
requireAdminToken();

// 수동 입력 or DB 기반 자동 계산
$roundNumber = (int) ($_POST['round_number'] ?? 0);
$drawDate = $_POST['draw_date'] ?? '';

if ($roundNumber <= 0 || $drawDate === '') {
    // DB에서 다음 회차 조회
    $nextRound = RoundHelper::getNextRound();

    if (isset($nextRound['error'])) {
        errorResponse(500, 'NO_CURRENT_ROUND', 'DB에 기존 회차가 없습니다. 먼저 마이그레이션을 실행해주세요.');
    }

    $roundNumber = $roundNumber > 0 ? $roundNumber : $nextRound['round_number'];
    $drawDate = $drawDate !== '' ? $drawDate : $nextRound['draw_date'];
    logInfo('회차 DB 기반 자동 계산 적용', ['round_number' => $roundNumber, 'draw_date' => $drawDate], 'api');
}

logInfo('매주 번호 생성 API 호출', ['round_number' => $roundNumber, 'draw_date' => $drawDate], 'api');

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
