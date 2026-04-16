<?php

declare(strict_types=1);

/**
 * POST /api/redraw.php   — 고유번호 다시 뽑기
 *   name_ids[] = 대상 name id 목록 (없으면 전체)
 *
 * GET  /api/redraw.php   — active 이름 목록 조회 (다시 뽑기 UI용)
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/models/Name.php';
require_once __DIR__ . '/../src/services/DrawService.php';

require_once __DIR__ . '/../src/helpers/response.php';
header('Content-Type: application/json; charset=utf-8');
requireAdminToken();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // active 이름 목록 반환
    $name = new Name();
    $names = $name->getActive();
    $list = array_map(fn($n) => [
        'id'             => (int) $n['id'],
        'name'           => $n['name'],
        'has_fixed'      => !empty($n['fixed_numbers']),
    ], $names);

    echo json_encode(['success' => true, 'data' => $list]);
    exit;
}

// POST — 다시 뽑기 실행
$rawIds = $_POST['name_ids'] ?? null;

// name_ids가 전달되지 않거나 빈 배열이면 전체 대상
if (empty($rawIds)) {
    $nameIds = null; // DrawService에서 전체로 처리
} else {
    $nameIds = array_map('intval', (array) $rawIds);
    $nameIds = array_filter($nameIds, fn($id) => $id > 0);
    $nameIds = array_values($nameIds);
    if (empty($nameIds)) {
        echo json_encode(['success' => false, 'error' => 'INVALID_IDS']);
        exit(1);
    }
}

$service = new DrawService();
$result = $service->redrawFixedNumbers($nameIds);

if (isset($result['error'])) {
    http_response_code(400);
    echo json_encode(['success' => false, ...$result]);
    exit(1);
}

echo json_encode(['success' => true, 'data' => $result]);
