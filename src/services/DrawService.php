<?php

declare(strict_types=1);

/**
 * DrawService
 *
 * 번호 생성 비즈니스 로직 오케스트레이션
 * - 대기열 처리 (pending → active + 고유번호)
 * - 매주 번호 생성 (active 전체 → user_rounds)
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Round.php';
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/GeminiService.php';

class DrawService
{
    private User $user;
    private Round $round;
    private Prompt $prompt;
    private GeminiService $gemini;

    /** 한 번의 API 호출에 포함할 이름 수 */
    private int $chunkSize = 15;

    /** API 호출 간 딜레이 (초) */
    private int $delaySeconds = 3;

    public function __construct()
    {
        $this->user = new User();
        $this->round = new Round();
        $this->prompt = new Prompt();
        $this->gemini = new GeminiService();
    }

    /**
     * 대기열 처리: pending 이름에 고유번호 생성 + active 전환
     */
    public function processPending(): array
    {
        $startTime = microtime(true);

        $pendingUsers = $this->user->getPending();
        if (empty($pendingUsers)) {
            return ['processed' => 0, 'failed' => 0, 'elapsed_seconds' => 0];
        }

        // fixed 프롬프트 조회
        $activePrompt = $this->prompt->getActive('fixed');
        if (!$activePrompt) {
            return ['error' => 'NO_ACTIVE_PROMPT', 'message' => '활성 fixed 프롬프트가 없습니다.'];
        }

        $processed = 0;
        $failed = 0;

        // 청크 분할
        $chunks = array_chunk($pendingUsers, $this->chunkSize);

        foreach ($chunks as $i => $chunk) {
            $names = array_column($chunk, 'name');

            // Gemini API 호출
            $results = $this->gemini->generateNumbers($activePrompt['content'], $names);

            // 결과 매칭 + DB 저장
            foreach ($chunk as $pendingUser) {
                $matched = $this->findResultByName($results, $pendingUser['name']);

                if ($matched) {
                    $this->user->activateWithFixedNumbers(
                        (int) $pendingUser['id'],
                        $matched['numbers']
                    );
                    $processed++;
                } else {
                    $failed++;
                    error_log("[DrawService] 고유번호 생성 실패: {$pendingUser['name']}");
                }
            }

            // 다음 청크 전 딜레이 (마지막 청크 제외)
            if ($i < count($chunks) - 1) {
                sleep($this->delaySeconds);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        return [
            'processed' => $processed,
            'failed' => $failed,
            'elapsed_seconds' => $elapsed,
        ];
    }

    /**
     * 매주 번호 생성: 새 회차 생성 + active 전체에 번호 부여
     */
    public function drawWeekly(int $roundNumber, string $drawDate): array
    {
        $startTime = microtime(true);

        // 회차 중복 체크
        $existingRound = $this->round->findByRoundNumber($roundNumber);
        if ($existingRound) {
            return ['error' => 'ROUND_ALREADY_EXISTS', 'message' => '이미 존재하는 회차입니다.'];
        }

        // weekly 프롬프트 조회
        $activePrompt = $this->prompt->getActive('weekly');
        if (!$activePrompt) {
            return ['error' => 'NO_ACTIVE_PROMPT', 'message' => '활성 weekly 프롬프트가 없습니다.'];
        }

        // 회차 생성
        $round = $this->round->create($roundNumber, $drawDate);
        $roundId = (int) $round['id'];

        // active 사용자 전체 조회
        $activeUsers = $this->user->getActive();
        if (empty($activeUsers)) {
            return [
                'round_id' => $roundId,
                'round_number' => $roundNumber,
                'total_users' => 0,
                'generated' => 0,
                'failed' => 0,
                'elapsed_seconds' => 0,
            ];
        }

        $generated = 0;
        $failed = 0;

        // 청크 분할
        $chunks = array_chunk($activeUsers, $this->chunkSize);

        foreach ($chunks as $i => $chunk) {
            $names = array_column($chunk, 'name');

            // Gemini API 호출
            $results = $this->gemini->generateNumbers($activePrompt['content'], $names);

            // 결과 매칭 + DB 저장
            foreach ($chunk as $activeUser) {
                $matched = $this->findResultByName($results, $activeUser['name']);

                if ($matched) {
                    $this->round->saveUserNumbers(
                        (int) $activeUser['id'],
                        $roundId,
                        $matched['numbers']
                    );
                    $generated++;
                } else {
                    $failed++;
                    error_log("[DrawService] 주간번호 생성 실패: {$activeUser['name']}");
                }
            }

            // 다음 청크 전 딜레이 (마지막 청크 제외)
            if ($i < count($chunks) - 1) {
                sleep($this->delaySeconds);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);

        return [
            'round_id' => $roundId,
            'round_number' => $roundNumber,
            'total_users' => count($activeUsers),
            'generated' => $generated,
            'failed' => $failed,
            'elapsed_seconds' => $elapsed,
        ];
    }

    /**
     * Gemini 응답에서 이름으로 결과 찾기
     */
    private function findResultByName(array $results, string $name): ?array
    {
        foreach ($results as $r) {
            if ($r['name'] === $name) {
                return $r;
            }
        }
        return null;
    }
}
