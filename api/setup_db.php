<?php
require __DIR__ . '/../src/config/database.php';
try {
    $pdo = getDatabase();
    // FK 해제 후 전체 테이블 드랍
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $t) {
        $pdo->exec("DROP TABLE `$t`");
    }
    // 스키마 실행
    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $pdo->exec($sql);
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
    
    // 현재 회차 세팅
    require __DIR__ . '/../src/helpers/RoundHelper.php';
    RoundHelper::ensureCurrentRound();
    
    echo "<h1>DB Reset SUCCESS!</h1><p>모든 데이터베이스 구조가 성공적으로 최신화되었습니다.</p>";
} catch (Exception $e) {
    echo "<h1>Error</h1><pre>" . $e->getMessage() . "</pre>";
}
