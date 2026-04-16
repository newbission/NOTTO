<?php
declare(strict_types=1);

// 로그를 볼 수 있도록 글로벌로 변경
ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../src/config/database.php';

try {
    $pdo = getDatabase();
    
    // Check if 'names' table exists reliably
    $result = $pdo->query("SHOW TABLES LIKE 'names'");
    $tables = $result ? $result->fetchAll() : [];
    $exists = count($tables) > 0;
    
    if (!$exists) {
        echo "🆕 최초 DB 설치 — schema.sql 실행\n";
        
        $sqlPath = __DIR__ . '/../database/schema.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("schema.sql not found at $sqlPath");
        }
        
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
             throw new Exception("Failed to read schema.sql");
        }
        
        // Enable ATTR_EMULATE_PREPARES specifically for multi-query execution
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
        $pdo->exec($sql);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        
        // Restore it back to false
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $tablesAfter = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo "✅ schema.sql 적용 완료. 만들어진 테이블:\n";
        print_r($tablesAfter);
    } else {
        echo "📦 기존 DB 감지 — 마이그레이션 확인\n";
        require __DIR__ . '/../src/helpers/migrator.php';
    }
    
    // 현재 회차 세팅
    require_once __DIR__ . '/../src/helpers/RoundHelper.php';
    RoundHelper::ensureCurrentRound();
    echo "📅 현재 회차 동기화 완료\n";

} catch (Throwable $e) {
    echo "❌ 초기화 중 에러 발생: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
