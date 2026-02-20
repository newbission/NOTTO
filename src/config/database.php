<?php

declare(strict_types=1);

/**
 * Database Configuration
 *
 * .env 파일을 로드하고 PDO 연결을 생성합니다.
 * InfinityFree 호환 (MySQL 8.0 / MariaDB 11.4)
 */

/**
 * .env 파일을 파싱하여 환경 변수로 로드
 */
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // 주석 무시
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // KEY=VALUE 파싱
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 시스템 환경변수(Docker 등)가 이미 설정된 경우 스킵
            if (getenv($key) !== false) {
                continue;
            }

            // 따옴표 제거
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * 환경 변수 읽기 (우선순위: putenv > $_ENV > 기본값)
 */
function env(string $key, string $default = ''): string
{
    return getenv($key) ?: ($_ENV[$key] ?? $default);
}

// .env 로드 (프로젝트 루트에서 찾기)
$envPath = __DIR__ . '/../../.env';
loadEnv($envPath);

/**
 * 글로벌 에러/예외 핸들러 — 모든 PHP 오류를 JSON으로 반환
 * (모든 API가 이 파일을 require하므로 자동 적용)
 */
function _nottoJsonErrorResponse(string $code, string $message, int $httpCode = 500): void
{
    // 이미 출력이 시작되었으면 버퍼 클리어
    if (ob_get_length()) {
        ob_end_clean();
    }

    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (Throwable $e) {
    @require_once __DIR__ . '/../helpers/logger.php';
    logError('Uncaught Exception', [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], 'error');

    $message = (function_exists('env') && env('APP_DEBUG') === 'true')
        ? $e->getMessage()
        : '서버 내부 오류가 발생했습니다.';

    _nottoJsonErrorResponse('INTERNAL_ERROR', $message);
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    // E_NOTICE, E_DEPRECATED 등은 무시 (@ 연산자 포함)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    @require_once __DIR__ . '/../helpers/logger.php';
    logError('PHP Error', [
        'errno' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
    ], 'error');

    $message = (function_exists('env') && env('APP_DEBUG') === 'true')
        ? "{$errstr} in {$errfile}:{$errline}"
        : '서버 내부 오류가 발생했습니다.';

    _nottoJsonErrorResponse('PHP_ERROR', $message);
    return true;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        @require_once __DIR__ . '/../helpers/logger.php';
        logError('Fatal Error', [
            'type' => $error['type'],
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ], 'error');

        $message = (function_exists('env') && env('APP_DEBUG') === 'true')
            ? $error['message']
            : '서버 내부 오류가 발생했습니다.';

        _nottoJsonErrorResponse('FATAL_ERROR', $message);
    }
});

/**
 * PDO 연결 생성
 */
function getDatabase(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $dbName = env('DB_NAME', 'notto');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        @require_once __DIR__ . '/../helpers/logger.php';
        logError('DB 연결 실패', ['error' => $e->getMessage()], 'db');

        $message = env('APP_DEBUG') === 'true'
            ? 'DB 연결 실패: ' . $e->getMessage()
            : '데이터베이스에 연결할 수 없습니다.';

        _nottoJsonErrorResponse('DB_CONNECTION_ERROR', $message);
    }

    return $pdo;
}
