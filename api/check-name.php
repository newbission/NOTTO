<?php

declare(strict_types=1);

/**
 * GET /api/check-name.php — 이름 중복 체크
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/models/User.php';

requireMethod('GET');

$name = trim($_GET['name'] ?? '');

if ($name === '') {
    errorResponse(400, 'NAME_EMPTY', '이름을 입력해주세요.');
}

$user = new User();
$existing = $user->findByName($name);

jsonResponse([
    'exists' => $existing !== null,
    'status' => $existing['status'] ?? null,
]);
