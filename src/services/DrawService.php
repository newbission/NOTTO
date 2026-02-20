<?php

declare(strict_types=1);

/**
 * DrawService
 *
 * 번호 생성 비즈니스 로직 오케스트레이션
 * - 대기열 처리 (pending → active + 고유번호)
 * - 매주 번호 생성 (active 전체 → name_rounds)
 */

require_once __DIR__ . '/../models/Name.php';
require_once __DIR__ . '/../models/Round.php';
require_once __DIR__ . '/../models/Prompt.php';
require_once __DIR__ . '/GeminiService.php';
require_once __DIR__ . '/../helpers/logger.php';

class DrawService
{
    private Name $name;
    private Round $round;
    private Prompt $prompt;
    private GeminiService $gemini;

    /** 한 번의 API 호출에 포함할 이름 수 */
    private int $chunkSize = 15;

    /** API 호출 간 딜레이 (초) */
    private int $delaySeconds = 3;

    public function __construct()
    {
        $this->name = new Name();
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

        $pendingNames = $this->name->getPending();
        if (empty($pendingNames)) {
            logInfo('대기열 처리: pending 이름 없음', [], 'draw');
            return ['processed' => 0, 'failed' => 0, 'elapsed_seconds' => 0];
        }

        logInfo('대기열 처리 시작', ['pending_count' => count($pendingNames)], 'draw');

        // fixed 프롬프트 조회
        $activePrompt = $this->prompt->getActive('fixed');
        if (!$activePrompt) {
            logError('활성 fixed 프롬프트 없음', [], 'draw');
            return ['error' => 'NO_ACTIVE_PROMPT', 'message' => '활성 fixed 프롬프트가 없습니다.'];
        }

        $processed = 0;
        $failed = 0;

        // 청크 분할
        $chunks = array_chunk($pendingNames, $this->chunkSize);
        logInfo('청크 분할 완료', ['total_chunks' => count($chunks), 'chunk_size' => $this->chunkSize], 'draw');

        foreach ($chunks as $i => $chunk) {
            $names = array_column($chunk, 'name');
            logInfo("청크 처리 시작", ['chunk' => $i + 1, 'names' => $names], 'draw');

            // Gemini API 호출
            $results = $this->gemini->generateNumbers($activePrompt['content'], $names);

            // 결과 매칭 + DB 저장
            foreach ($chunk as $pendingName) {
                $matched = $this->findResultByName($results, $pendingName['name']);

                if ($matched) {
                    $this->name->activateWithFixedNumbers(
                        (int) $pendingName['id'],
                        $matched['numbers']
                    );
                    $processed++;
                    logInfo('고유번호 생성 성공', [
                        'name' => $pendingName['name'],
                        'numbers' => $matched['numbers']
                    ], 'draw');
                } else {
                    $failed++;
                    logError('고유번호 생성 실패', ['name' => $pendingName['name']], 'draw');
                }
            }

            // 다음 청크 전 딜레이 (마지막 청크 제외)
            if ($i < count($chunks) - 1) {
                logInfo("청크 간 딜레이", ['seconds' => $this->delaySeconds], 'draw');
                sleep($this->delaySeconds);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        logInfo('대기열 처리 완료', ['processed' => $processed, 'failed' => $failed, 'elapsed' => $elapsed], 'draw');

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
        logInfo('주간 번호 생성 시작', ['round' => $roundNumber, 'date' => $drawDate], 'draw');

        // 대기열에 pending 이름이 있으면 먼저 처리
        $pendingResult = $this->processPending();
        if ($pendingResult['processed'] ?? 0 > 0) {
            logInfo('주간 생성 전 대기열 처리 완료', $pendingResult, 'draw');
        }

        // 회차 중복 체크
        $existingRound = $this->round->findByRoundNumber($roundNumber);
        if ($existingRound) {
            logWarn('회차 중복', ['round' => $roundNumber], 'draw');
            return ['error' => 'ROUND_ALREADY_EXISTS', 'message' => '이미 존재하는 회차입니다.'];
        }

        // weekly 프롬프트 조회
        $activePrompt = $this->prompt->getActive('weekly');
        if (!$activePrompt) {
            logError('활성 weekly 프롬프트 없음', [], 'draw');
            return ['error' => 'NO_ACTIVE_PROMPT', 'message' => '활성 weekly 프롬프트가 없습니다.'];
        }

        // 회차 생성
        $round = $this->round->create($roundNumber, $drawDate);
        $roundId = (int) $round['id'];

        // active 이름 전체 조회
        $activeNames = $this->name->getActive();
        if (empty($activeNames)) {
            logInfo('active 이름 없음 — 빈 회차 생성됨', ['round' => $roundNumber], 'draw');
            return [
                'round_id' => $roundId,
                'round_number' => $roundNumber,
                'total_names' => 0,
                'generated' => 0,
                'failed' => 0,
                'elapsed_seconds' => 0,
            ];
        }

        logInfo('번호 생성 대상', ['active_count' => count($activeNames)], 'draw');

        $generated = 0;
        $failed = 0;

        // 청크 분할
        $chunks = array_chunk($activeNames, $this->chunkSize);

        foreach ($chunks as $i => $chunk) {
            $names = array_column($chunk, 'name');
            logInfo("청크 처리", ['chunk' => $i + 1, 'total_chunks' => count($chunks), 'names' => $names], 'draw');

            // Gemini API 호출
            $results = $this->gemini->generateNumbers($activePrompt['content'], $names);

            // 결과 매칭 + DB 저장
            foreach ($chunk as $activeName) {
                $matched = $this->findResultByName($results, $activeName['name']);

                if ($matched) {
                    $this->round->saveNameNumbers(
                        (int) $activeName['id'],
                        $roundId,
                        $matched['numbers']
                    );
                    $generated++;
                } else {
                    $failed++;
                    logError('주간번호 생성 실패', ['name' => $activeName['name'], 'round' => $roundNumber], 'draw');
                }
            }

            // 다음 청크 전 딜레이 (마지막 청크 제외)
            if ($i < count($chunks) - 1) {
                sleep($this->delaySeconds);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        logInfo('주간 번호 생성 완료', [
            'round' => $roundNumber,
            'total' => count($activeNames),
            'generated' => $generated,
            'failed' => $failed,
            'elapsed' => $elapsed
        ], 'draw');

        return [
            'round_id' => $roundId,
            'round_number' => $roundNumber,
            'total_names' => count($activeNames),
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
