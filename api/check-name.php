<?php

declare(strict_types=1);

/**
 * GET /api/check-name.php — 이름 중복 체크
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

logInfo('이름 중복 체크 요청', ['name' => $name], 'api');

$nameModel = new Name();
$existing = $nameModel->findByName($name);

$exists = $existing && $existing['status'] !== 'deleted';
logInfo('이름 중복 체크 결과', ['name' => $name, 'exists' => $exists, 'status' => $existing['status'] ?? null], 'api');

jsonResponse([
    'exists' => $exists,
    'status' => $existing['status'] ?? null,
]);
