<?php

declare(strict_types=1);

/**
 * GeminiService
 *
 * Google Gemini API와 통신하여 로또 번호를 생성합니다.
 * REST API 직접 호출 (별도 SDK 미사용)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? env('GEMINI_API_KEY');
        $this->model = $model ?? env('GEMINI_MODEL', 'gemini-2.5-flash');
    }

    /**
     * 여러 이름에 대해 번호 생성
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

        $response = $this->httpPost($url, $requestBody);

        if ($response === null) {
            return [];
        }

        return $this->parseResponse($response, $names);
    }

    /**
     * HTTP POST 요청
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

        logInfo('Gemini API 호출 성공', ['results_count' => count($parsed)], 'gemini');
        return $parsed;
    }

    /**
     * Gemini 응답 파싱 + 이름 매칭
     *
     * @param array $parsed Gemini가 반환한 JSON 배열
     * @param string[] $originalNames 요청한 이름 목록
     * @return array 정리된 결과
     */
    private function parseResponse(array $parsed, array $originalNames): array
    {
        $results = [];

        foreach ($parsed as $item) {
            if (!isset($item['name'], $item['numbers']) || !is_array($item['numbers'])) {
                continue;
            }

            $numbers = array_map('intval', $item['numbers']);

            // 번호 유효성 검증 (6개, 1~45, 중복 없음)
            $validNumbers = array_filter($numbers, fn($n) => $n >= 1 && $n <= 45);
            $validNumbers = array_values(array_unique($validNumbers));

            if (count($validNumbers) < 6) {
                continue; // 유효하지 않은 번호는 스킵
            }

            // 6개만 취하고 정렬
            $validNumbers = array_slice($validNumbers, 0, 6);
            sort($validNumbers);

            $results[] = [
                'name' => $item['name'],
                'numbers' => $validNumbers,
            ];
        }

        return $results;
    }
}
