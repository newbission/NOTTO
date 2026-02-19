<?php

declare(strict_types=1);

/**
 * GET /api/search.php — 이름 부분 검색
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/validator.php';
require_once __DIR__ . '/../src/models/User.php';

requireMethod('GET');

$query = trim($_GET['q'] ?? '');

if ($query === '') {
    errorResponse(400, 'QUERY_EMPTY', '검색어를 입력해주세요.');
}

$pagination = parsePagination();
$user = new User();

$results = $user->search($query, $pagination['offset'], $pagination['per_page']);
$total = $user->searchCount($query);

// JSON 필드 디코딩
$data = array_map(function ($row) {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'status' => $row['status'],
        'weekly_numbers' => $row['weekly_numbers'] ? json_decode($row['weekly_numbers'], true) : null,
        'round_number' => $row['round_number'] ? (int) $row['round_number'] : null,
        'winning_numbers' => $row['winning_numbers'] ? json_decode($row['winning_numbers'], true) : null,
        'bonus_number' => $row['bonus_number'] ? (int) $row['bonus_number'] : null,
        'matched_count' => $row['matched_count'] !== null ? (int) $row['matched_count'] : null,
    ];
}, $results);

jsonResponse($data, [
    'page' => $pagination['page'],
    'per_page' => $pagination['per_page'],
    'total' => $total,
    'query' => $query,
]);
