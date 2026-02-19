<?php

declare(strict_types=1);

/**
 * GET /api/users.php — 전체 사용자 목록 (인피니티 스크롤)
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/validator.php';
require_once __DIR__ . '/../src/models/User.php';

requireMethod('GET');

$pagination = parsePagination();
$sort = parseSort(['newest', 'oldest', 'name_asc', 'name_desc']);
$orderBy = sortToOrderBy($sort);

$user = new User();
$results = $user->getAll($orderBy, $pagination['offset'], $pagination['per_page']);
$total = $user->countAll();

$data = array_map(function ($row) {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'status' => $row['status'],
        'weekly_numbers' => isset($row['weekly_numbers']) && $row['weekly_numbers']
            ? json_decode($row['weekly_numbers'], true) : null,
        'round_number' => isset($row['round_number']) && $row['round_number']
            ? (int) $row['round_number'] : null,
        'matched_count' => isset($row['matched_count']) && $row['matched_count'] !== null
            ? (int) $row['matched_count'] : null,
    ];
}, $results);

$hasMore = ($pagination['offset'] + $pagination['per_page']) < $total;

jsonResponse($data, [
    'page' => $pagination['page'],
    'per_page' => $pagination['per_page'],
    'total' => $total,
    'has_more' => $hasMore,
    'sort' => $sort,
]);
