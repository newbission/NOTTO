<?php

declare(strict_types=1);

/**
 * RoundHelper
 *
 * 로또 회차 관리 유틸리티 (DB 기반)
 * rounds 테이블에서 최신 회차를 조회하여 현재 회차 정보를 제공
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

class RoundHelper
{
    /**
     * 현재 (최신) 회차 정보 조회
     * rounds 테이블에서 가장 큰 round_number를 가진 레코드 반환
     */
    public static function getCurrentRoundInfo(): ?array
    {
        $pdo = getDatabase();
        $stmt = $pdo->query(
            "SELECT round_number, draw_date, winning_numbers, bonus_number
             FROM rounds ORDER BY round_number DESC LIMIT 1"
        );
        $result = $stmt->fetch();

        if (!$result) {
            logWarn('회차 정보 없음 — rounds 테이블이 비어있음', [], 'round');
            return null;
        }

        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $drawDate = $result['draw_date'];
        $isDrawDay = $now->format('Y-m-d') === $drawDate;
        $hasDrawn = $result['winning_numbers'] !== null;

        return [
            'round_number' => (int) $result['round_number'],
            'draw_date' => $drawDate,
            'is_draw_day' => $isDrawDay,
            'has_drawn' => $hasDrawn,
        ];
    }

    /**
     * 다음 회차 계산 (날짜 기반 방어 로직 포함)
     *
     * - 최신 회차의 draw_date가 아직 안 지났으면 → NOT_YET 에러
     * - 여러 주가 건너뛰어졌으면 → skipped_rounds 정보 포함
     * - 정상이면 → 바로 다음 1회차 정보 반환
     */
    public static function getNextRound(): array
    {
        $current = self::getCurrentRoundInfo();

        if (!$current) {
            logError('다음 회차 계산 불가 — 기존 회차 없음', [], 'round');
            return ['error' => 'NO_CURRENT_ROUND'];
        }

        $tz = new DateTimeZone('Asia/Seoul');
        $now = new DateTime('now', $tz);
        $latestDrawDate = new DateTime($current['draw_date'], $tz);

        // 최신 회차의 draw_date가 아직 지나지 않았으면 → 아직 현재 회차 기간 중
        if ($now->format('Y-m-d') <= $latestDrawDate->format('Y-m-d')) {
            logInfo('다음 회차 생성 거부 — 현재 회차 기간 중', [
                'latest_round' => $current['round_number'],
                'draw_date' => $current['draw_date'],
                'today' => $now->format('Y-m-d'),
            ], 'round');
            return [
                'error' => 'NOT_YET',
                'latest_round' => $current['round_number'],
                'draw_date' => $current['draw_date'],
                'today' => $now->format('Y-m-d'),
            ];
        }

        // draw_date 이후 며칠 경과했는지 계산
        $daysDiff = (int) $latestDrawDate->diff($now)->days;
        $weeksPassed = (int) ceil($daysDiff / 7);

        // 최소 1주 (draw_date 다음날 ~ +6일 = 다음 회차)
        if ($weeksPassed < 1) {
            $weeksPassed = 1;
        }

        $skippedRounds = $weeksPassed - 1;
        $nextRoundNumber = $current['round_number'] + 1;
        $nextDrawDate = clone $latestDrawDate;
        $nextDrawDate->modify('+7 days');

        if ($skippedRounds > 0) {
            logWarn('건너뛴 회차 감지', [
                'skipped' => $skippedRounds,
                'expected_next' => $nextRoundNumber,
                'latest_in_db' => $current['round_number'],
            ], 'round');
        }

        return [
            'round_number' => $nextRoundNumber,
            'draw_date' => $nextDrawDate->format('Y-m-d'),
            'skipped_rounds' => $skippedRounds,
        ];
    }
}
