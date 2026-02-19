<?php

declare(strict_types=1);

/**
 * GET /api/fixed.php — 고유번호 조회
 *
 * ?name=홍길동
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
$result = $user->getFixedNumbers($name);

if (!$result) {
    errorResponse(404, 'NAME_NOT_FOUND', '등록되지 않은 이름입니다.');
}

if ($result['status'] === 'pending') {
    jsonResponse([
        'id' => (int) $result['id'],
        'name' => $result['name'],
        'status' => 'pending',
        'fixed_numbers' => null,
        'message' => '고유번호 생성 대기중입니다.',
        'created_at' => $result['created_at'],
    ]);
}

jsonResponse([
    'id' => (int) $result['id'],
    'name' => $result['name'],
    'status' => $result['status'],
    'fixed_numbers' => $result['fixed_numbers']
        ? json_decode($result['fixed_numbers'], true) : null,
    'created_at' => $result['created_at'],
]);
