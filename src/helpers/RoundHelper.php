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
     * 다음 회차 생성 (현재 최신 회차 + 1, 추첨일 + 7일)
     * drawWeekly에서 호출 시 사용
     */
    public static function getNextRound(): array
    {
        $current = self::getCurrentRoundInfo();

        if (!$current) {
            // rounds 테이블이 비어있으면 에러
            logError('다음 회차 계산 불가 — 기존 회차 없음', [], 'round');
            return ['error' => 'NO_CURRENT_ROUND'];
        }

        $nextRound = $current['round_number'] + 1;
        $nextDate = new DateTime($current['draw_date'], new DateTimeZone('Asia/Seoul'));
        $nextDate->modify('+7 days');

        return [
            'round_number' => $nextRound,
            'draw_date' => $nextDate->format('Y-m-d'),
        ];
    }
}
