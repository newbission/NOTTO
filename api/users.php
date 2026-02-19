<?php

declare(strict_types=1);

/**
 * GET /api/users.php → names.php — 전체 이름 목록
 *
 * ?page=1&per_page=20&sort=newest
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/validator.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Name.php';

requireMethod('GET');

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$sort = $_GET['sort'] ?? 'newest';
$offset = ($page - 1) * $perPage;

$orderBy = sortToOrderBy($sort);

logInfo('이름 목록 요청', ['page' => $page, 'per_page' => $perPage, 'sort' => $sort], 'api');

$nameModel = new Name();
$results = $nameModel->getAll($orderBy, $offset, $perPage);
$total = $nameModel->countAll();

// 결과 정리
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

logInfo('이름 목록 응답', ['count' => count($data), 'total' => $total, 'page' => $page], 'api');

jsonResponse($data, [
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'has_more' => ($page * $perPage) < $total,
    'sort' => $sort,
]);
