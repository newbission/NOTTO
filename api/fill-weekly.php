<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/services/DrawService.php';
require_once __DIR__ . '/../src/helpers/RoundHelper.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../src/helpers/response.php';
requireAdminToken();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // 현재 회차에서 주간번호 누락된 이름 수 반환
    $pdo = getDatabase();
    $currentRound = RoundHelper::ensureCurrentRound();
    $roundId = (int) $currentRound['id'];

    $stmt = $pdo->prepare("
        SELECT n.id, n.name
        FROM names n
        LEFT JOIN name_rounds nr ON nr.name_id = n.id AND nr.round_id = ?
        WHERE n.status = 'active' AND nr.id IS NULL
        ORDER BY n.id
    ");
    $stmt->execute([$roundId]);
    $missing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'round_number' => (int) $currentRound['round_number'],
        'draw_date' => $currentRound['draw_date'],
        'missing_count' => count($missing),
        'missing_names' => array_column($missing, 'name'),
    ]);
    exit;
}

if ($method === 'POST') {
    $service = new DrawService();
    $result = $service->fillMissingWeekly();
    echo json_encode(array_merge(['success' => !isset($result['error'])], $result));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'METHOD_NOT_ALLOWED']);
