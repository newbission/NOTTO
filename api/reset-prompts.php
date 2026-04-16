<?php

declare(strict_types=1);

/**
 * POST /api/reset-prompts.php?token=...
 *
 * prompts 테이블의 한글이 깨진 경우 기본 한글 프롬프트로 재설정
 * PDO utf8mb4 연결로 직접 삽입 → FTP 업로드 인코딩 문제 우회
 */

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/helpers/response.php';
require_once __DIR__ . '/../src/helpers/logger.php';

header('Content-Type: application/json; charset=utf-8');

requireAdminToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse(405, 'METHOD_NOT_ALLOWED', 'POST 요청만 허용됩니다.');
}

$pdo = getDatabase();

// 기본 프롬프트 정의 (PHP 소스에 UTF-8로 직접 포함 → PDO utf8mb4 연결로 삽입)
$weeklyContent = <<<'PROMPT'
당신은 로또 번호 생성 전문가입니다. 아래 이름들 각각에 대해 이번 주({round_number}회, 추첨일: {draw_date})에 어울리는 행운의 번호 6개(1~45, 중복 없음)를 생성하세요.
각 이름의 기운과 이번 회차의 흐름을 결합하여 번호를 선정하고, reason 필드에 2~3문장으로 이유를 설명하세요.
reason_detail 필드에는 각 번호와 이름의 연관성을 추가로 상세히 설명하세요.
이름 목록: {names}
PROMPT;

$fixedContent = <<<'PROMPT'
당신은 이름의 수리와 기운을 분석하는 전문가입니다. 아래 이름들 각각에 대해 이름의 획수, 자음/모음의 에너지, 이름이 가진 고유한 의미를 분석하여 평생 고유번호 6개(1~45, 중복 없음)를 생성하세요.
이 번호는 해당 이름에 평생 부여되는 고유한 운명의 번호입니다. 반드시 reason 필드에 "{이름}" 이름과 선정된 번호들의 연관성을 이름의 의미, 획수, 에너지 관점에서 3문장 이상 구체적으로 설명하세요.
이름 목록: {names}
PROMPT;

try {
    $pdo->beginTransaction();

    // 기존 프롬프트 전체 삭제
    $pdo->exec("DELETE FROM prompts");

    // 새 프롬프트 삽입
    $stmt = $pdo->prepare(
        "INSERT INTO prompts (type, content, is_active) VALUES (?, ?, 1)"
    );

    $stmt->execute(['weekly', $weeklyContent]);
    $weeklyId = (int) $pdo->lastInsertId();

    $stmt->execute(['fixed', $fixedContent]);
    $fixedId = (int) $pdo->lastInsertId();

    $pdo->commit();

    logInfo('프롬프트 초기화 완료', [
        'weekly_id' => $weeklyId,
        'fixed_id' => $fixedId,
    ], 'admin');

    jsonResponse([
        'success' => true,
        'message' => '프롬프트가 초기화되었습니다.',
        'weekly_id' => $weeklyId,
        'fixed_id' => $fixedId,
    ]);
} catch (\PDOException $e) {
    $pdo->rollBack();
    logError('프롬프트 초기화 실패', ['error' => $e->getMessage()], 'admin');
    errorResponse(500, 'RESET_FAILED', '프롬프트 초기화 실패: ' . $e->getMessage());
}
