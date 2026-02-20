<?php

declare(strict_types=1);

/**
 * GET /api/logs.php — 관리자 전용 로그 조회 API
 *
 * InfinityFree 등 터미널 접근이 불가한 환경에서 로그를 확인합니다.
 *
 * 사용법:
 *   날짜 폴더 목록:   GET /api/logs.php?token=ADMIN_TOKEN
 *   특정 날짜 파일 목록: GET /api/logs.php?token=ADMIN_TOKEN&date=2026-02-21
 *   특정 로그 조회:    GET /api/logs.php?token=ADMIN_TOKEN&date=2026-02-21&file=api.log&lines=50
 *   로그 삭제:        GET /api/logs.php?token=ADMIN_TOKEN&date=2026-02-21&file=api.log&action=delete
 *   날짜 폴더 삭제:    GET /api/logs.php?token=ADMIN_TOKEN&date=2026-02-21&action=delete
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

requireMethod('GET');
requireAdminToken();

$logBaseDir = realpath(getLogBaseDir());

if (!$logBaseDir || !is_dir($logBaseDir)) {
    errorResponse(500, 'LOGS_DIR_NOT_FOUND', 'logs 디렉토리를 찾을 수 없습니다.');
}

$requestedDate = $_GET['date'] ?? null;
$requestedFile = $_GET['file'] ?? null;
$action = $_GET['action'] ?? null;

// ─── 날짜 폴더 목록 조회 ───
if (!$requestedDate) {
    $dates = [];
    $items = scandir($logBaseDir, SCANDIR_SORT_DESCENDING);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitkeep') {
            continue;
        }

        $fullPath = $logBaseDir . '/' . $item;
        if (!is_dir($fullPath)) {
            // 기존 flat 로그 파일도 표시 (마이그레이션 전 파일)
            continue;
        }

        // 날짜 폴더 내 파일 수와 총 크기 계산
        $fileCount = 0;
        $totalSize = 0;
        $dirItems = scandir($fullPath);
        foreach ($dirItems as $f) {
            $fp = $fullPath . '/' . $f;
            if (is_file($fp)) {
                $fileCount++;
                $totalSize += filesize($fp);
            }
        }

        $dates[] = [
            'date' => $item,
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'total_size_human' => _formatBytes($totalSize),
        ];
    }

    jsonResponse([
        'log_dir' => $logBaseDir,
        'writable' => is_writable($logBaseDir),
        'dates' => $dates,
        'count' => count($dates),
    ]);
}

// ─── 날짜 형식 검증 (Path Traversal 방지) ───
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
    errorResponse(400, 'INVALID_DATE', '날짜 형식이 올바르지 않습니다. (YYYY-MM-DD)');
}

$dateDir = $logBaseDir . '/' . $requestedDate;

if (!is_dir($dateDir)) {
    errorResponse(404, 'DATE_NOT_FOUND', "해당 날짜의 로그가 없습니다: {$requestedDate}");
}

// ─── 날짜 폴더 삭제 ───
if (!$requestedFile && $action === 'delete') {
    $dirItems = scandir($dateDir);
    foreach ($dirItems as $f) {
        $fp = $dateDir . '/' . $f;
        if (is_file($fp)) {
            @unlink($fp);
        }
    }
    $removed = @rmdir($dateDir);
    if (!$removed) {
        errorResponse(500, 'DELETE_FAILED', '날짜 폴더 삭제에 실패했습니다.');
    }
    logInfo('날짜 폴더 삭제', ['date' => $requestedDate], 'api');
    jsonResponse(['deleted_date' => $requestedDate]);
}

// ─── 특정 날짜의 파일 목록 조회 ───
if (!$requestedFile) {
    $files = [];
    $items = scandir($dateDir, SCANDIR_SORT_ASCENDING);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $dateDir . '/' . $item;
        if (!is_file($fullPath)) {
            continue;
        }

        $files[] = [
            'name' => $item,
            'channel' => pathinfo($item, PATHINFO_FILENAME),
            'size' => filesize($fullPath),
            'size_human' => _formatBytes(filesize($fullPath)),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
        ];
    }

    jsonResponse([
        'date' => $requestedDate,
        'log_dir' => $dateDir,
        'files' => $files,
        'count' => count($files),
    ]);
}

// ─── Path Traversal 방지 ───
$safeFilename = basename($requestedFile);
$filePath = $dateDir . '/' . $safeFilename;
$realFilePath = realpath($filePath);

$realDateDir = realpath($dateDir);
if (!$realFilePath || !str_starts_with($realFilePath, $realDateDir) || !is_file($realFilePath)) {
    errorResponse(404, 'FILE_NOT_FOUND', "로그 파일을 찾을 수 없습니다: {$requestedDate}/{$safeFilename}");
}

// ─── 파일 삭제 액션 ───
if ($action === 'delete') {
    $deleted = @unlink($realFilePath);
    if (!$deleted) {
        errorResponse(500, 'DELETE_FAILED', '로그 파일 삭제에 실패했습니다.');
    }
    logInfo('로그 파일 삭제', ['date' => $requestedDate, 'file' => $safeFilename], 'api');
    jsonResponse(['deleted' => "{$requestedDate}/{$safeFilename}"]);
}

// ─── 특정 파일 조회 (마지막 N줄) ───
$lines = max(1, min(500, (int) ($_GET['lines'] ?? 100)));
$content = @file_get_contents($realFilePath);

if ($content === false) {
    errorResponse(500, 'READ_FAILED', '로그 파일을 읽을 수 없습니다.');
}

$allLines = explode("\n", trim($content));
$totalLines = count($allLines);
$slicedLines = array_slice($allLines, -$lines);

jsonResponse([
    'date' => $requestedDate,
    'file' => $safeFilename,
    'total_lines' => $totalLines,
    'showing_lines' => count($slicedLines),
    'lines' => $slicedLines,
]);

// ─── Helper ───
function _formatBytes(int $bytes): string
{
    if ($bytes < 1024)
        return $bytes . ' B';
    if ($bytes < 1048576)
        return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

