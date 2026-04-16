<?php

declare(strict_types=1);

/**
 * 최신 고유번호 목록 조회 API
 * 메인 페이지 초기 로드 시 표시할 최신 10~20개의 고유번호를 반환합니다.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Name.php';

try {
    $limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 12;

    $nameModel = new Name();
    
    // Name.php에 별도 메서드가 없으므로 직접 쿼리 실행
    $pdo = getDatabase();
    $stmt = $pdo->prepare(
        "SELECT n.id, n.name, n.fixed_numbers, n.fixed_reason, n.created_at,
                (SELECT COUNT(*) FROM name_rounds nr WHERE nr.name_id = n.id) AS participation_count
         FROM names n
         WHERE n.status = 'active' AND n.fixed_numbers IS NOT NULL
         ORDER BY n.updated_at DESC, n.id DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function ($row) {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'fixed_numbers' => $row['fixed_numbers'] ? json_decode($row['fixed_numbers'], true) : null,
            'fixed_reason' => $row['fixed_reason'],
            'participation_count' => (int) $row['participation_count'],
            'created_at' => $row['created_at'],
        ];
    }, $results);

    jsonResponse($formatted);

} catch (Exception $e) {
    logError('최신 고유번호 조회 실패', ['error' => $e->getMessage()], 'api');
    errorResponse(500, 'SERVER_ERROR', $e->getMessage());
}
