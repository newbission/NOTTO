<?php

declare(strict_types=1);

/**
 * POST /api/register.php — 이름 등록
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/validator.php';
require_once __DIR__ . '/../src/models/User.php';

requireMethod('POST');

// 입력 처리
$name = sanitizeName($_POST['name'] ?? '');

// 유효성 검증
$error = validateName($name);
if ($error !== null) {
    $messages = [
        'NAME_EMPTY' => '이름을 입력해주세요.',
        'NAME_TOO_LONG' => '이름은 20자 이내로 입력해주세요.',
    ];
    errorResponse(400, $error, $messages[$error] ?? '잘못된 입력입니다.');
}

$user = new User();

// 중복 체크
$existing = $user->findByName($name);
if ($existing !== null) {
    if ($existing['status'] === 'deleted') {
        errorResponse(400, 'NAME_DELETED', '관리자에 의해 삭제된 이름입니다.');
    }
    errorResponse(400, 'NAME_ALREADY_EXISTS', '이미 등록된 이름입니다.');
}

// 등록
$newUser = $user->create($name);

jsonResponse([
    'id' => $newUser['id'],
    'name' => $newUser['name'],
    'status' => $newUser['status'],
    'message' => '등록이 완료되었습니다. 곧 번호가 생성됩니다.',
], [], 201);
