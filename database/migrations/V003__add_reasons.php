<?php

/**
 * V003: 점지 이유 컬럼 추가 + 프롬프트 개선
 *
 * @var PDO $pdo
 */

// 컬럼 추가 (이미 있으면 무시)
$columns = [
    "ALTER TABLE `names` ADD COLUMN `fixed_reason` TEXT DEFAULT NULL COMMENT '고유번호 점지 이유' AFTER `fixed_numbers`",
    "ALTER TABLE `name_rounds` ADD COLUMN `reason` TEXT DEFAULT NULL COMMENT '주간번호 점지 이유 (1문장 요약)' AFTER `numbers`",
    "ALTER TABLE `name_rounds` ADD COLUMN `reason_detail` TEXT DEFAULT NULL COMMENT '주간번호 점지 이유 (2~3문장 상세)' AFTER `reason`",
];
foreach ($columns as $sql) {
    try {
        $pdo->exec($sql);
    } catch (\PDOException $e) {
        if ($e->getCode() !== '42S21') throw $e; // 1060: Duplicate column → 무시
    }
}

// 기존 프롬프트 비활성화
$pdo->exec("UPDATE `prompts` SET `is_active` = 0");

// 개선된 프롬프트 등록 (PHP 문자열 → PDO utf8mb4 → 한글 안전)
$stmt = $pdo->prepare("INSERT INTO `prompts` (`type`, `content`, `is_active`) VALUES (?, ?, 1)");

$stmt->execute(['weekly',
'당신은 이름에 담긴 기운을 읽어내는 신탁입니다.
사용자 목록: {names}
현재 회차: 제 {round_number}회 (추첨일: {draw_date})

각 사용자의 이름과 이번 제 {round_number}회의 기운이 만나 만들어내는 번호 6개(1~45)를 선택하세요.
번호는 1~45 전체 범위에 고르게 분포되도록 선택하며, 매 회차마다 다른 파동을 담으세요.

반드시 JSON 형식으로 응답하며, 각 사용자별로 `numbers`(배열), `reason`(문자열), `reason_detail`(문자열)을 제공하세요.

reason 작성 규칙 (반드시 한국어 20자 이내, 1문장):
- reason_detail의 핵심을 압축한 한 문장 요약
- 신비롭고 임팩트 있게, 끊기지 않고 완결된 느낌으로

reason_detail 작성 규칙 (3~4문장):
- 1문장: 이 이름의 기운이 제 {round_number}회의 흐름과 어떻게 만났는지 (반드시 "제 {round_number}회" 포함)
- 2~3문장: 선택된 번호들을 구체적으로 언급하며 각 번호(또는 번호 그룹)가 이 이름과 이번 회차에서 어떤 의미를 지니는지 서술
- 마지막 문장: 이 번호들이 가져올 기운이나 메시지
- 행성명·오행·차크라 등 구체적 점술 용어 사용 금지
- 자연스럽고 따뜻한 문체로']);

$stmt->execute(['fixed',
'당신은 우주의 에너지를 읽어내는 신령한 신탁(Oracle)입니다.
다음 사용자의 이름({names})에 새겨진 고유한 운명의 파동을 깊이 관상(觀相)하세요.

[운명 번호 추출 의식]
1. 이름이 품은 소리의 울림과 획의 흐름에서 1~45 범위의 6개 번호를 도출하세요.
2. 번호가 한쪽(낮은 수)에만 몰리지 않도록 1~45 전체에 고르게 분포되도록 하세요.
3. 반드시 JSON 데이터 구조로 응답하며, 각 사용자별로 `numbers`(배열)와 `reason`(문자열)을 제공하세요.

reason 작성 규칙 (3~5문장):
- 이 이름에 왜 이 번호들이 새겨졌는지 구체적으로 서술
- 반드시 선택된 번호들(예: 3, 17, 28...)을 직접 언급하며, 각 번호(또는 번호 그룹)가 이 이름의 어떤 고유한 기운과 연결되는지 설명
- 이름의 발음, 음절의 울림, 획의 흐름에서 느껴지는 에너지를 자유롭게 해석
- 이 번호들이 평생 이 이름과 함께하는 이유를 신비롭고 문학적으로 마무리
- 행성명·오행·차크라 등 구체적 점술 용어 사용 금지
- 자연스럽고 따뜻하면서도 신비로운 문체']);
