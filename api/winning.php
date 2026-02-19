<?php

declare(strict_types=1);

/**
 * GET /api/winning.php — 당첨번호 입력 (🔒 관리자)
 *
 * ?token=XXX&round_number=1160&numbers=5,12,17,22,33,40&bonus=28
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/models/Round.php';

requireMethod('GET');
requireAdminToken();

$roundNumber = (int) ($_GET['round_number'] ?? 0);
$numbersStr = $_GET['numbers'] ?? '';
$bonus = (int) ($_GET['bonus'] ?? 0);

// 검증
if ($roundNumber <= 0) {
    errorResponse(400, 'INVALID_ROUND', '유효한 회차 번호를 입력해주세요.');
}

$numbers = array_map('intval', explode(',', $numbersStr));
if (count($numbers) !== 6) {
    errorResponse(400, 'INVALID_NUMBERS', '당첨번호 6개를 콤마로 구분하여 입력해주세요.');
}

foreach ($numbers as $n) {
    if ($n < 1 || $n > 45) {
        errorResponse(400, 'INVALID_NUMBERS', '번호는 1~45 범위여야 합니다.');
    }
}

if ($bonus < 1 || $bonus > 45) {
    errorResponse(400, 'INVALID_BONUS', '보너스 번호는 1~45 범위여야 합니다.');
}

$round = new Round();

// 회차 존재 확인
$existingRound = $round->findByRoundNumber($roundNumber);
if (!$existingRound) {
    errorResponse(404, 'ROUND_NOT_FOUND', '해당 회차를 찾을 수 없습니다.');
}

// 당첨번호 저장
sort($numbers);
$round->setWinningNumbers($roundNumber, $numbers, $bonus);

// matched_count 계산
$updated = $round->calculateMatches((int) $existingRound['id']);

jsonResponse([
    'round_number' => $roundNumber,
    'winning_numbers' => $numbers,
    'bonus_number' => $bonus,
    'matched_updated' => $updated,
]);
