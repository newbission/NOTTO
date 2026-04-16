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
    // 현재 회차 + active 이름 목록 (현재 주간번호 포함)
    $pdo = getDatabase();
    $currentRound = RoundHelper::ensureCurrentRound();
    $roundId = (int) $currentRound['id'];

    $stmt = $pdo->prepare("
        SELECT n.id, n.name,
               nr.numbers AS weekly_numbers,
               nr.reason  AS weekly_reason
        FROM names n
        LEFT JOIN name_rounds nr ON nr.name_id = n.id AND nr.round_id = ?
        WHERE n.status = 'active'
        ORDER BY n.name
    ");
    $stmt->execute([$roundId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(fn($r) => [
        'id'             => (int) $r['id'],
        'name'           => $r['name'],
        'has_weekly'     => $r['weekly_numbers'] !== null,
        'weekly_numbers' => $r['weekly_numbers'] ? json_decode($r['weekly_numbers'], true) : null,
        'weekly_reason'  => $r['weekly_reason'],
    ], $rows);

    echo json_encode([
        'success'      => true,
        'round_number' => (int) $currentRound['round_number'],
        'draw_date'    => $currentRound['draw_date'],
        'data'         => $data,
    ]);
    exit;
}

if ($method === 'POST') {
    $nameIds = null;
    if (!empty($_POST['name_ids'])) {
        $nameIds = array_map('intval', (array) $_POST['name_ids']);
    }

    $service = new DrawService();
    $result = $service->redrawWeekly($nameIds);
    echo json_encode(array_merge(['success' => !isset($result['error'])], $result));
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'METHOD_NOT_ALLOWED']);
