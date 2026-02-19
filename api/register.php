<?php

declare(strict_types=1);

/**
 * POST /api/register.php — 이름 등록
 *
 * Body: name=홍길동
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/validator.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Name.php';

requireMethod('POST');

$rawName = $_POST['name'] ?? '';
$name = validateName($rawName);

logInfo('이름 등록 요청', ['name' => $name, 'ip' => $_SERVER['REMOTE_ADDR'] ?? ''], 'api');

$nameModel = new Name();

// 중복 체크
$existing = $nameModel->findByName($name);
if ($existing) {
    if ($existing['status'] === 'deleted') {
        // 삭제된 이름 재등록 → pending으로 복원
        $nameModel->updateStatus((int) $existing['id'], 'pending');
        logInfo('삭제된 이름 재등록 (pending 복원)', ['id' => $existing['id'], 'name' => $name], 'api');
        jsonResponse([
            'id' => (int) $existing['id'],
            'name' => $existing['name'],
            'status' => 'pending',
            'message' => '등록이 완료되었습니다. 곧 번호가 생성됩니다.',
        ], [], 201);
    }

    logInfo('이름 등록 중복', ['name' => $name, 'existing_status' => $existing['status']], 'api');
    errorResponse(409, 'NAME_EXISTS', '이미 등록된 이름입니다.');
}

$result = $nameModel->create($name);
logInfo('이름 등록 성공', ['id' => $result['id'], 'name' => $name], 'api');

jsonResponse([
    'id' => (int) $result['id'],
    'name' => $result['name'],
    'status' => 'pending',
    'message' => '등록이 완료되었습니다. 곧 번호가 생성됩니다.',
], [], 201);
