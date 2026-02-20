<?php

declare(strict_types=1);

/**
 * Logger Helper
 *
 * 날짜별 폴더로 로그를 정리합니다.
 * 경로: logs/{YYYY-MM-DD}/{channel}.log
 */

/**
 * 로그 베이스 디렉토리 경로 (프로젝트 루트 기준)
 */
function getLogBaseDir(): string
{
    return __DIR__ . '/../../logs';
}

/**
 * 날짜별 로그 디렉토리 경로를 반환하고, 없으면 생성합니다.
 */
function getLogDir(?string $date = null): string
{
    $date = $date ?? date('Y-m-d');
    $dir = getLogBaseDir() . '/' . $date;

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * 로그 메시지 기록
 *
 * @param string $level  로그 레벨 (INFO, WARN, ERROR, DEBUG)
 * @param string $message 메시지
 * @param array  $context 추가 컨텍스트 데이터
 * @param string $channel 채널명 (파일명, 기본: 'app')
 */
function writeLog(string $level, string $message, array $context = [], string $channel = 'app'): void
{
    $logDir = getLogDir();
    $time = date('Y-m-d H:i:s');
    $fileName = "{$channel}.log";
    $filePath = "{$logDir}/{$fileName}";

    $logLine = "[{$time}] [{$level}] {$message}";

    if (!empty($context)) {
        $logLine .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $logLine .= PHP_EOL;

    $written = @file_put_contents($filePath, $logLine, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        // 파일 쓰기 실패 시 PHP 내장 error_log로 폴백
        error_log("[NOTTO][{$channel}][{$level}] {$message} " . json_encode($context, JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 편의 함수들 — 직접 문자열 전달 (PHP 내장 LOG_INFO 상수 충돌 방지)
 */
function logInfo(string $message, array $context = [], string $channel = 'app'): void
{
    writeLog('INFO', $message, $context, $channel);
}

function logWarn(string $message, array $context = [], string $channel = 'app'): void
{
    writeLog('WARN', $message, $context, $channel);
}

function logError(string $message, array $context = [], string $channel = 'app'): void
{
    writeLog('ERROR', $message, $context, $channel);
}

function logDebug(string $message, array $context = [], string $channel = 'app'): void
{
    if (function_exists('env') && env('APP_DEBUG') === 'true') {
        writeLog('DEBUG', $message, $context, $channel);
    }
}
