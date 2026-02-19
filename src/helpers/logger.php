<?php

declare(strict_types=1);

/**
 * Logger Helper
 *
 * 날짜별 로그 파일로 기록합니다.
 * 경로: logs/{channel}_{YYYY-MM-DD}.log
 */

/**
 * 로그 디렉토리 경로 (프로젝트 루트 기준)
 */
function getLogDir(): string
{
    $dir = __DIR__ . '/../../logs';

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
 * @param string $channel 채널명 (파일 접두사, 기본: 'app')
 */
function writeLog(string $level, string $message, array $context = [], string $channel = 'app'): void
{
    $logDir = getLogDir();
    $date = date('Y-m-d');
    $time = date('Y-m-d H:i:s');
    $fileName = "{$channel}_{$date}.log";
    $filePath = "{$logDir}/{$fileName}";

    $logLine = "[{$time}] [{$level}] {$message}";

    if (!empty($context)) {
        $logLine .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $logLine .= PHP_EOL;

    @file_put_contents($filePath, $logLine, FILE_APPEND | LOCK_EX);
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
