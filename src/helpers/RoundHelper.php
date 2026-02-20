<?php

declare(strict_types=1);

/**
 * RoundHelper
 *
 * 로또 회차 계산 유틸리티
 * 앵커: 1212회 = 2026-02-21 (토요일 추첨)
 * 규칙: 일요일부터 다음 회차 시작
 */

class RoundHelper
{
    /** 앵커 회차 번호 */
    const ANCHOR_ROUND = 1212;

    /** 앵커 추첨일 (토요일) */
    const ANCHOR_DATE = '2026-02-21';

    /**
     * 주어진 날짜 기준 현재 회차 번호 계산
     *
     * 로또는 매주 토요일 추첨, 일요일부터 다음 회차.
     * - 2026-02-21 (토) → 1212회
     * - 2026-02-22 (일) → 1213회
     * - 2026-02-28 (토) → 1213회
     * - 2026-03-01 (일) → 1214회
     */
    public static function getCurrentRound(?string $date = null): int
    {
        $now = $date ? new DateTime($date) : new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $anchor = new DateTime(self::ANCHOR_DATE, new DateTimeZone('Asia/Seoul'));

        // 각 날짜를 해당 주의 토요일(추첨일)로 정규화
        // PHP: 6=Saturday, 0=Sunday
        $nowDay = (int) $now->format('w'); // 0(일)~6(토)
        $anchorDay = (int) $anchor->format('w');

        // 현재 날짜가 속한 회차의 토요일 계산
        // 일(0)→이전 토요일 = -1, 월(1)→다음 토요일 = +5, ..., 토(6)→ 0
        if ($nowDay === 0) {
            // 일요일이면 이미 다음 회차 → 다음 토요일 기준
            $nowSaturday = (clone $now)->modify('+6 days');
        } else {
            // 월~토: 이번 주 토요일 기준
            $daysUntilSat = 6 - $nowDay;
            $nowSaturday = (clone $now)->modify("+{$daysUntilSat} days");
        }

        $anchorSaturday = clone $anchor; // 이미 토요일

        $diff = $anchorSaturday->diff($nowSaturday);
        $weeks = (int) ($diff->days / 7);

        if ($nowSaturday < $anchorSaturday) {
            $weeks = -$weeks;
        }

        return self::ANCHOR_ROUND + $weeks;
    }

    /**
     * 회차 번호 → 추첨일(토요일) 반환
     */
    public static function getDrawDate(?int $round = null): string
    {
        $round = $round ?? self::getCurrentRound();
        $weeksDiff = $round - self::ANCHOR_ROUND;

        $date = new DateTime(self::ANCHOR_DATE, new DateTimeZone('Asia/Seoul'));
        if ($weeksDiff >= 0) {
            $date->modify("+{$weeksDiff} weeks");
        } else {
            $absDiff = abs($weeksDiff);
            $date->modify("-{$absDiff} weeks");
        }

        return $date->format('Y-m-d');
    }

    /**
     * 현재 회차 종합 정보 반환
     */
    public static function getCurrentRoundInfo(): array
    {
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        $currentRound = self::getCurrentRound();
        $drawDate = self::getDrawDate($currentRound);
        $drawDateTime = new DateTime($drawDate, new DateTimeZone('Asia/Seoul'));

        $isDrawDay = $now->format('Y-m-d') === $drawDate;

        return [
            'round_number' => $currentRound,
            'draw_date' => $drawDate,
            'is_draw_day' => $isDrawDay,
        ];
    }
}
