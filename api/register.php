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
$name = trim($rawName);

$validationError = validateName($name);
if ($validationError) {
    $messages = [
        'NAME_EMPTY' => '이름을 입력해주세요.',
        'NAME_TOO_LONG' => '이름은 최대 20자까지 가능합니다.',
    ];
    errorResponse(400, $validationError, $messages[$validationError] ?? '유효하지 않은 이름입니다.');
}

logInfo('이름 등록 요청', ['name' => $name, 'ip' => $_SERVER['REMOTE_ADDR'] ?? ''], 'api');

$nameModel = new Name();

// 중복 체크
$existing = $nameModel->findByName($name);
if ($existing) {
    if ($existing['status'] === 'rejected') {
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

// DIRECT_REGISTER=true 이면 즉시 고유번호 + 주간번호 생성 (cron 없이 바로 처리)
if (env('DIRECT_REGISTER') === 'true') {
    logInfo('DIRECT_REGISTER 활성 — 즉시 처리 시작', ['id' => $result['id']], 'api');

    require_once __DIR__ . '/../src/services/DrawService.php';
    $service = new DrawService();

    // 1) 고유번호 생성 + active 전환
    $processResult = $service->processPending();
    logInfo('DIRECT_REGISTER 고유번호 처리 완료', $processResult, 'api');

    // 2) 최신 회차 주간번호 생성
    $weeklyResult = $service->generateWeeklyForName((int) $result['id'], $name);
    logInfo('DIRECT_REGISTER 주간번호 처리 완료', $weeklyResult, 'api');

    // 처리 후 최신 데이터 다시 조회
    $updated = $nameModel->findByName($name);
    jsonResponse([
        'id' => (int) $updated['id'],
        'name' => $updated['name'],
        'status' => $updated['status'],
        'fixed_numbers' => $updated['fixed_numbers'] ?? null,
        'message' => '등록이 완료되었습니다. 고유번호와 주간번호가 생성되었습니다!',
    ], [], 201);
}

jsonResponse([
    'id' => (int) $result['id'],
    'name' => $result['name'],
    'status' => 'pending',
    'message' => '등록이 완료되었습니다. 곧 번호가 생성됩니다.',
], [], 201);
