<?php

declare(strict_types=1);

/**
 * GET /api/round.php — 현재 로또 회차 정보
 *
 * DB에서 최신 회차를 조회하여 반환
 *
 * 응답 예시:
 * {
 *   "success": true,
 *   "data": {
 *     "round_number": 1212,
 *     "draw_date": "2026-02-21",
 *     "is_draw_day": false,
 *     "has_drawn": false
 *   }
 * }
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/helpers/RoundHelper.php';

requireMethod('GET');

$info = RoundHelper::getCurrentRoundInfo();

if (!$info) {
    jsonResponse(null);
} else {
    logInfo('회차 정보 조회', $info, 'api');
    jsonResponse($info);
}
