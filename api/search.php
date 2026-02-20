<?php

declare(strict_types=1);

/**
 * GET /api/search.php — 이름 부분 검색
 *
 * ?q=김&page=1&per_page=20
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Name.php';

requireMethod('GET');

$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

if ($query === '') {
    errorResponse(400, 'QUERY_EMPTY', '검색어를 입력해주세요.');
}

logInfo('이름 검색 요청', ['query' => $query, 'page' => $page, 'per_page' => $perPage], 'api');

$nameModel = new Name();
$results = $nameModel->search($query, $offset, $perPage);
$total = $nameModel->searchCount($query);

// 결과 정리
$data = array_map(function ($row) {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'status' => $row['status'],
        'weekly_numbers' => $row['weekly_numbers']
            ? json_decode($row['weekly_numbers'], true) : null,
        'round_number' => $row['round_number'] ? (int) $row['round_number'] : null,
        'winning_numbers' => $row['winning_numbers']
            ? json_decode($row['winning_numbers'], true) : null,
        'bonus_number' => $row['bonus_number'] ? (int) $row['bonus_number'] : null,
        'matched_count' => $row['matched_count'] !== null ? (int) $row['matched_count'] : null,
        'participation_count' => (int) ($row['participation_count'] ?? 0),
    ];
}, $results);

logInfo('이름 검색 응답', ['query' => $query, 'results' => count($data), 'total' => $total], 'api');

jsonResponse($data, [
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'query' => $query,
]);
