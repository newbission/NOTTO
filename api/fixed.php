<?php

declare(strict_types=1);

/**
 * GET /api/fixed.php — 고유번호 조회
 *
 * ?name=홍길동
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Name.php';

requireMethod('GET');

$name = trim($_GET['name'] ?? '');

if ($name === '') {
    errorResponse(400, 'NAME_EMPTY', '이름을 입력해주세요.');
}

logInfo('고유번호 조회 요청', ['name' => $name], 'api');

$nameModel = new Name();
$result = $nameModel->getFixedNumbers($name);

if (!$result) {
    logInfo('고유번호 조회 실패 — 미등록 이름', ['name' => $name], 'api');
    errorResponse(404, 'NAME_NOT_FOUND', '등록되지 않은 이름입니다.');
}

if ($result['status'] === 'pending') {
    logInfo('고유번호 조회 — 아직 pending 상태', ['name' => $name], 'api');
    jsonResponse([
        'id' => (int) $result['id'],
        'name' => $result['name'],
        'status' => 'pending',
        'fixed_numbers' => null,
        'message' => '고유번호 생성 대기중입니다.',
        'created_at' => $result['created_at'],
    ]);
}

$fixedNumbers = $result['fixed_numbers']
    ? json_decode($result['fixed_numbers'], true) : null;

logInfo('고유번호 조회 성공', ['name' => $name, 'numbers' => $fixedNumbers], 'api');

jsonResponse([
    'id' => (int) $result['id'],
    'name' => $result['name'],
    'status' => $result['status'],
    'fixed_numbers' => $fixedNumbers,
    'created_at' => $result['created_at'],
]);
