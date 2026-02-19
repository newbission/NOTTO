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

        if (env('APP_DEBUG') === 'true') {
            die('DB 연결 실패: ' . $e->getMessage());
        }
        die('서버 오류가 발생했습니다.');
    }

    return $pdo;
}
