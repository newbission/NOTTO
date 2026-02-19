<?php

declare(strict_types=1);

/**
 * GET /api/prompts.php — 프롬프트 관리 (🔒 관리자)
 *
 * ?token=XXX&action=list
 * ?token=XXX&action=create&type=weekly&content=...&activate=true
 * ?token=XXX&action=activate&id=3
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';
require_once __DIR__ . '/../src/models/Prompt.php';

requireMethod('GET');
requireAdminToken();

$action = $_GET['action'] ?? 'list';
$prompt = new Prompt();

logInfo('프롬프트 관리 API 호출', ['action' => $action], 'api');

switch ($action) {
    case 'list':
        $all = $prompt->getAll();
        $data = array_map(fn($p) => [
            'id' => (int) $p['id'],
            'type' => $p['type'],
            'content' => $p['content'],
            'is_active' => (bool) $p['is_active'],
            'created_at' => $p['created_at'],
            'updated_at' => $p['updated_at'],
        ], $all);
        logInfo('프롬프트 목록 조회', ['count' => count($data)], 'api');
        jsonResponse($data);

    case 'create':
        $type = $_GET['type'] ?? '';
        $content = $_GET['content'] ?? '';
        $activate = ($_GET['activate'] ?? 'false') === 'true';

        if (!in_array($type, ['weekly', 'fixed'], true)) {
            errorResponse(400, 'INVALID_TYPE', 'type은 weekly 또는 fixed만 가능합니다.');
        }
        if ($content === '') {
            errorResponse(400, 'CONTENT_EMPTY', '프롬프트 내용을 입력해주세요.');
        }

        $created = $prompt->create($type, $content, $activate);
        logInfo('프롬프트 생성 완료', ['id' => $created['id'], 'type' => $type, 'activate' => $activate], 'api');
        jsonResponse($created, [], 201);

    case 'activate':
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            errorResponse(400, 'INVALID_ID', '유효한 프롬프트 ID를 입력해주세요.');
        }

        $success = $prompt->activate($id);
        if (!$success) {
            errorResponse(404, 'PROMPT_NOT_FOUND', '해당 프롬프트를 찾을 수 없습니다.');
        }

        logInfo('프롬프트 활성화 완료', ['id' => $id], 'api');
        jsonResponse(['activated' => $id]);

    default:
        errorResponse(400, 'INVALID_ACTION', 'action은 list, create, activate 중 하나여야 합니다.');
}
