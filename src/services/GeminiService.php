<?php

declare(strict_types=1);

/**
 * GeminiService
 *
 * Google Gemini API와 통신하여 로또 번호를 생성합니다.
 * REST API 직접 호출 (별도 SDK 미사용)
 *
 * 기능:
 * - generateNumbers(): 여러 이름에 대해 번호 생성 (기존 호환)
 * - generateBothNumbers(): 단일 이름에 대해 고유+주간 번호 동시 생성
 * - 재시도 로직 (최대 3회, 지수 백오프)
 * - 패턴 감지 (등차수열, 연속번호 등 의심 패턴 필터링)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    /** 최대 재시도 횟수 */
    private int $maxRetries = 3;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? env('GEMINI_API_KEY');
        $this->model = $model ?? env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    /**
     * 여러 이름에 대해 번호 생성 (기존 호환)
     *
     * @param string $promptTemplate 프롬프트 ({names} 플레이스홀더 포함)
     * @param string[] $names 이름 배열
     * @return array [['name' => '홍길동', 'numbers' => [1,2,3,4,5,6]], ...]
     */
    public function generateNumbers(string $promptTemplate, array $names): array
    {
        logInfo('Gemini API 호출 시작', ['names_count' => count($names), 'model' => $this->model], 'gemini');

        $namesJson = json_encode($names, JSON_UNESCAPED_UNICODE);
        $prompt = str_replace('{names}', $namesJson, $promptTemplate);

        $requestBody = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'numbers' => [
                                'type' => 'ARRAY',
                                'items' => ['type' => 'INTEGER']
                            ]
                        ],
                        'required' => ['name', 'numbers']
                    ]
                ]
            ]
        ];

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $response = $this->httpPostWithRetry($url, $requestBody);

        if ($response === null) {
            return [];
        }

        return $this->parseResponse($response, $names);
    }

    /**
     * 단일 이름에 대해 고유번호 + 주간번호 동시 생성 (1회 API 호출)
     *
     * @param string $fixedPromptTemplate fixed 프롬프트 ({names} 플레이스홀더)
     * @param string $weeklyPromptTemplate weekly 프롬프트 ({names} 플레이스홀더)
     * @param string $name 이름
     * @return array|null ['fixed_numbers' => [...], 'weekly_numbers' => [...]] or null
     */
    public function generateBothNumbers(
        string $fixedPromptTemplate,
        string $weeklyPromptTemplate,
        string $name
    ): ?array {
        logInfo('Gemini 통합 호출 시작 (고유+주간)', ['name' => $name, 'model' => $this->model], 'gemini');

        $nameJson = json_encode([$name], JSON_UNESCAPED_UNICODE);
        $fixedPrompt = str_replace('{names}', $nameJson, $fixedPromptTemplate);
        $weeklyPrompt = str_replace('{names}', $nameJson, $weeklyPromptTemplate);

        $combinedPrompt = <<<PROMPT
아래 두 가지 작업을 수행해주세요. 반드시 JSON 형식으로 응답하세요.

[작업 1: 고유번호 생성]
{$fixedPrompt}

[작업 2: 주간번호 생성]
{$weeklyPrompt}

중요: 고유번호와 주간번호는 반드시 서로 다른 번호 조합이어야 합니다.
PROMPT;

        $requestBody = [
            'contents' => [
                ['parts' => [['text' => $combinedPrompt]]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'fixed_numbers' => [
                            'type' => 'ARRAY',
                            'items' => ['type' => 'INTEGER']
                        ],
                        'weekly_numbers' => [
                            'type' => 'ARRAY',
                            'items' => ['type' => 'INTEGER']
                        ]
                    ],
                    'required' => ['fixed_numbers', 'weekly_numbers']
                ]
            ]
        ];

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $response = $this->httpPostWithRetry($url, $requestBody);

        if ($response === null) {
            return null;
        }

        // 응답 검증
        if (!isset($response['fixed_numbers'], $response['weekly_numbers'])) {
            logError('Gemini 통합 응답 형식 오류', ['response' => $response], 'gemini');
            return null;
        }

        $fixed = $this->validateAndCleanNumbers($response['fixed_numbers']);
        $weekly = $this->validateAndCleanNumbers($response['weekly_numbers']);

        if ($fixed === null || $weekly === null) {
            logError('Gemini 통합 응답 번호 검증 실패', [
                'fixed_valid' => $fixed !== null,
                'weekly_valid' => $weekly !== null,
            ], 'gemini');
            return null;
        }

        logInfo('Gemini 통합 호출 성공', [
            'name' => $name,
            'fixed' => $fixed,
            'weekly' => $weekly,
        ], 'gemini');

        return [
            'fixed_numbers' => $fixed,
            'weekly_numbers' => $weekly,
        ];
    }

    // ─── HTTP 통신 ───

    /**
     * HTTP POST 요청 (재시도 포함)
     */
    private function httpPostWithRetry(string $url, array $body): ?array
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $result = $this->httpPost($url, $body);

            if ($result !== null) {
                if ($attempt > 1) {
                    logInfo('Gemini 재시도 성공', ['attempt' => $attempt], 'gemini');
                }
                return $result;
            }

            if ($attempt < $this->maxRetries) {
                $delay = $attempt * 2; // 2초, 4초
                logWarn('Gemini 재시도 대기', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'delay_seconds' => $delay,
                ], 'gemini');
                sleep($delay);
            }
        }

        logError('Gemini API 최대 재시도 초과', ['max_retries' => $this->maxRetries], 'gemini');
        return null;
    }

    /**
     * HTTP POST 요청 (단일)
     */
    private function httpPost(string $url, array $body): ?array
    {
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $jsonBody,
                'timeout' => 30,
                'ignore_errors' => true,
            ]
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            logError('Gemini API 호출 실패', ['url' => preg_replace('/key=[^&]+/', 'key=***', $url)], 'gemini');
            return null;
        }

        $decoded = json_decode($result, true);

        if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            logError('Gemini 응답 파싱 실패', ['response' => substr($result, 0, 300)], 'gemini');
            return null;
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'];
        $parsed = json_decode($text, true);

        if (!is_array($parsed)) {
            logError('Gemini JSON 파싱 실패', ['text' => substr($text, 0, 300)], 'gemini');
            return null;
        }

        logInfo('Gemini API 호출 성공', ['results_count' => is_array($parsed) ? count($parsed) : 1], 'gemini');
        return $parsed;
    }

    // ─── 응답 파싱 ───

    /**
     * Gemini 응답 파싱 + 이름 매칭 (배열 응답용)
     */
    private function parseResponse(array $parsed, array $originalNames): array
    {
        $results = [];

        foreach ($parsed as $item) {
            if (!isset($item['name'], $item['numbers']) || !is_array($item['numbers'])) {
                continue;
            }

            $validNumbers = $this->validateAndCleanNumbers($item['numbers']);

            if ($validNumbers === null) {
                logWarn('번호 검증 실패 — 스킵', ['name' => $item['name']], 'gemini');
                continue;
            }

            $results[] = [
                'name' => $item['name'],
                'numbers' => $validNumbers,
            ];
        }

        return $results;
    }

    // ─── 번호 검증 ───

    /**
     * 번호 유효성 검증 + 정리
     *
     * @return int[]|null 유효한 6개 번호 배열, 실패 시 null
     */
    private function validateAndCleanNumbers(array $numbers): ?array
    {
        $numbers = array_map('intval', $numbers);

        // 1~45 범위 필터
        $validNumbers = array_filter($numbers, fn($n) => $n >= 1 && $n <= 45);
        $validNumbers = array_values(array_unique($validNumbers));

        if (count($validNumbers) < 6) {
            return null;
        }

        // 6개만 취하고 정렬
        $validNumbers = array_slice($validNumbers, 0, 6);
        sort($validNumbers);

        // 패턴 감지
        if ($this->hasSuspiciousPattern($validNumbers)) {
            logWarn('의심 패턴 감지 — 번호 무효 처리', ['numbers' => $validNumbers], 'gemini');
            return null;
        }

        return $validNumbers;
    }

    /**
     * 의심스러운 패턴 감지
     *
     * - 등차수열 (모든 차이가 동일: [4,8,12,16,20,24])
     * - 연속 5개 이상 ([1,2,3,4,5,40])
     */
    private function hasSuspiciousPattern(array $numbers): bool
    {
        if (count($numbers) < 6) {
            return false;
        }

        // 차이값 계산
        $diffs = [];
        for ($i = 1; $i < count($numbers); $i++) {
            $diffs[] = $numbers[$i] - $numbers[$i - 1];
        }

        // 등차수열 감지 (모든 차이가 동일)
        if (count(array_unique($diffs)) === 1) {
            logInfo('등차수열 패턴 감지', ['numbers' => $numbers, 'diff' => $diffs[0]], 'gemini');
            return true;
        }

        // 연속 5개 이상 감지 (차이가 1인 것이 4개 이상 연속)
        $consecutiveOnes = 0;
        $maxConsecutiveOnes = 0;
        foreach ($diffs as $d) {
            if ($d === 1) {
                $consecutiveOnes++;
                $maxConsecutiveOnes = max($maxConsecutiveOnes, $consecutiveOnes);
            } else {
                $consecutiveOnes = 0;
            }
        }
        if ($maxConsecutiveOnes >= 4) { // 5개 연속 = diff가 4번 연속 1
            logInfo('연속번호 패턴 감지', ['numbers' => $numbers, 'consecutive' => $maxConsecutiveOnes + 1], 'gemini');
            return true;
        }

        return false;
    }
}
