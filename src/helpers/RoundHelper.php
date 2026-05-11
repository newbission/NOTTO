<?php

declare(strict_types=1);

/**
 * RoundHelper
 *
 * 로또 회차 관리 유틸리티
 *
 * 기준점: 로또 6/45 1회차 = 2002-12-07 (토요일)
 * 매주 토요일 추첨, 회차 = (토요일까지의 주 수) + 1
 *
 * - 현재 회차는 기준점에서 동적 계산
 * - DB에 회차가 없으면 현재 회차를 자동 생성
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/logger.php';

class RoundHelper
{
    /** 1회차 추첨일 (토요일) */
    private const EPOCH_DATE = '2002-12-07';
    private const EPOCH_ROUND = 1;

    /**
     * 기준점에서 특정 날짜의 회차를 계산
     * 로또는 매주 토요일 추첨 → 해당 주의 토요일 기준으로 회차 결정
     */
    public static function calculateRoundForDate(DateTime $date): array
    {
        $tz = new DateTimeZone('Asia/Seoul');
        $epoch = new DateTime(self::EPOCH_DATE, $tz);

        // 해당 날짜가 속한 주의 토요일 계산
        $target = clone $date;
        $dayOfWeek = (int) $target->format('w'); // 0=일, 6=토
        if ($dayOfWeek !== 6) {
            // 이번 주 토요일로 이동
            $daysToSaturday = (6 - $dayOfWeek) % 7;
            if ($daysToSaturday === 0) $daysToSaturday = 7; // 일요일이면 다음 토요일
            // 일요일(0)은 지난 주 추첨이 끝난 상태 → 다음 토요일
            if ($dayOfWeek === 0) {
                $target->modify('+6 days');
            } else {
                $target->modify("+{$daysToSaturday} days");
            }
        }

        $diffDays = (int) $epoch->diff($target)->days;
        $roundNumber = (int) ($diffDays / 7) + self::EPOCH_ROUND;

        return [
            'round_number' => $roundNumber,
            'draw_date' => $target->format('Y-m-d'),
        ];
    }

    /**
     * 현재 (이번 주) 회차 정보 계산
     */
    public static function getCurrentRoundCalculated(): array
    {
        $tz = new DateTimeZone('Asia/Seoul');
        $now = new DateTime('now', $tz);
        return self::calculateRoundForDate($now);
    }

    /**
     * 현재 회차 정보 조회 (DB 기반, 없으면 계산값 반환)
     */
    public static function getCurrentRoundInfo(): ?array
    {
        $pdo = getDatabase();
        $stmt = $pdo->query(
            "SELECT id, round_number, draw_date, winning_numbers, bonus_number
             FROM rounds ORDER BY round_number DESC LIMIT 1"
        );
        $result = $stmt->fetch();

        // DB에 회차가 없으면 계산값 반환
        if (!$result) {
            $calculated = self::getCurrentRoundCalculated();
            logWarn('rounds 테이블 비어있음 — 계산값 사용', $calculated, 'round');
            return [
                'round_number' => $calculated['round_number'],
                'draw_date' => $calculated['draw_date'],
                'is_draw_day' => false,
                'has_drawn' => false,
                'from_db' => false,
            ];
        }

        $tz = new DateTimeZone('Asia/Seoul');
        $now = new DateTime('now', $tz);
        $drawDate = $result['draw_date'];
        $isDrawDay = $now->format('Y-m-d') === $drawDate;
        $hasDrawn = $result['winning_numbers'] !== null;

        return [
            'id' => (int) $result['id'],
            'round_number' => (int) $result['round_number'],
            'draw_date' => $drawDate,
            'is_draw_day' => $isDrawDay,
            'has_drawn' => $hasDrawn,
            'from_db' => true,
        ];
    }

    /**
     * 현재 회차가 DB에 없으면 자동 생성
     * (docker-entrypoint 또는 register 시 호출)
     */
    public static function ensureCurrentRound(): array
    {
        $pdo = getDatabase();
        $calculated = self::getCurrentRoundCalculated();
        $roundNumber = $calculated['round_number'];
        $drawDate = $calculated['draw_date'];

        // 이미 존재하는지 확인
        $stmt = $pdo->prepare("SELECT id, round_number, draw_date FROM rounds WHERE round_number = ?");
        $stmt->execute([$roundNumber]);
        $existing = $stmt->fetch();

        if ($existing) {
            return [
                'id' => (int) $existing['id'],
                'round_number' => (int) $existing['round_number'],
                'draw_date' => $existing['draw_date'],
                'created' => false,
            ];
        }

        // 새 회차 생성
        $stmt = $pdo->prepare(
            "INSERT INTO rounds (round_number, draw_date) VALUES (?, ?)"
        );
        $stmt->execute([$roundNumber, $drawDate]);
        $newId = (int) $pdo->lastInsertId();

        logInfo('현재 회차 자동 생성', [
            'round_number' => $roundNumber,
            'draw_date' => $drawDate,
        ], 'round');

        return [
            'id' => $newId,
            'round_number' => $roundNumber,
            'draw_date' => $drawDate,
            'created' => true,
        ];
    }

    /**
     * 다음 회차 계산 (주간 추첨용)
     *
     * ensureCurrentRound()가 미리 빈 회차를 생성해둔 경우,
     * 해당 회차에 name_rounds가 0건이면 "아직 번호 미생성 회차"로 판단하여
     * NOT_YET 대신 해당 회차를 반환한다.
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
            // 단, 해당 회차에 name_rounds가 0건이면 ensureCurrentRound()가 빈 회차만 만든 상태
            // → 이 회차에 번호를 생성해야 하므로 NOT_YET 대신 해당 회차 반환
            if (isset($current['id'])) {
                $pdo = getDatabase();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM name_rounds WHERE round_id = ?");
                $stmt->execute([$current['id']]);
                $nameRoundsCount = (int) $stmt->fetchColumn();

                if ($nameRoundsCount === 0) {
                    logInfo('최신 회차에 번호 미생성 상태 — 해당 회차를 추첨 대상으로 반환', [
                        'round_number' => $current['round_number'],
                        'draw_date' => $current['draw_date'],
                    ], 'round');
                    return [
                        'round_number' => $current['round_number'],
                        'draw_date' => $current['draw_date'],
                        'skipped_rounds' => 0,
                        'reuse_existing' => true,
                    ];
                }
            }

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
