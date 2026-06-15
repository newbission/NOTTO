-- ============================================
-- NOTTO Database Schema (통합본)
-- 신규 설치 시 이 파일 하나만 실행하면 전체 DB 구성 완료
-- 현재 버전: V005
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- --------------------------------------------
-- 마이그레이션 버전 추적 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `schema_versions` (
    `version` VARCHAR(10) NOT NULL COMMENT '버전 번호 (V001, V002...)',
    `description` VARCHAR(255) NOT NULL COMMENT '마이그레이션 설명',
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 이름 관리 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `names` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL COMMENT '등록 이름 (UTF-8, 최대 20자)',
    `status` ENUM('pending','active','rejected') NOT NULL DEFAULT 'pending' COMMENT '상태',
    `fixed_numbers` JSON DEFAULT NULL COMMENT '고유번호 (6개, 1~45)',
    `fixed_reason` TEXT DEFAULT NULL COMMENT '고유번호 점지 이유',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 회차 관리 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `rounds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `round_number` INT NOT NULL COMMENT '회차 번호',
    `draw_date` DATE NOT NULL COMMENT '추첨 날짜',
    `winning_numbers` JSON DEFAULT NULL COMMENT '당첨번호 6개',
    `bonus_number` TINYINT DEFAULT NULL COMMENT '보너스 번호',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_round_number` (`round_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 이름-회차 참여 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `name_rounds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name_id` INT NOT NULL COMMENT 'names.id',
    `round_id` INT NOT NULL COMMENT 'rounds.id',
    `numbers` JSON NOT NULL COMMENT 'AI 생성 번호 6개',
    `reason` TEXT DEFAULT NULL COMMENT '주간번호 점지 이유 (1문장 요약)',
    `reason_detail` TEXT DEFAULT NULL COMMENT '주간번호 점지 이유 (3~4문장 상세)',
    `matched_count` TINYINT DEFAULT NULL COMMENT '적중 수',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name_round` (`name_id`, `round_id`),
    INDEX `idx_name_id` (`name_id`),
    INDEX `idx_round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 프롬프트 관리 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `prompts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `type` ENUM('weekly','fixed') NOT NULL COMMENT '프롬프트 용도',
    `content` TEXT NOT NULL COMMENT '프롬프트 내용',
    `is_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '현재 사용 여부',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_type_active` (`type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 초기 프롬프트 데이터 (시스템 필수)
-- --------------------------------------------
INSERT INTO `prompts` (`type`, `content`, `is_active`) VALUES
('weekly', '당신은 이름에 담긴 기운을 읽어내는 신탁입니다.\n사용자 목록: {names}\n현재 회차: 제 {round_number}회 (추첨일: {draw_date})\n\n각 사용자의 이름과 이번 제 {round_number}회의 기운이 만나 만들어내는 번호 6개(1~45)를 선택하세요.\n번호는 인위적으로 모든 번호대(0번대, 10번대, 20번대, 30번대, 40번대)에 고르게 분산시키지 마십시오. 실제 로또 당첨 번호처럼 특정 번호대에 여러 개의 번호가 쏠리거나, 아예 나오지 않는 번호대(공백)가 생기는 등 불규칙하고 역동적인 분포가 자연스럽게 나타나도록 하세요. 매 회차마다 다른 파동을 담으십시오.\n\n특히, 번호들이 일정한 간격으로 증가하거나 등차수열을 이루는 등 인위적인 수학적 패턴(예: 7씩 늘어나는 패턴 등)이 절대 나타나지 않도록 완벽히 불규칙하고 무작위적인 형태로 번호를 조합해야 합니다. 이름의 획수나 기운을 해석할 때 번호 간의 사칙연산 관계를 억지로 만들어내지 마십시오.\n\n반드시 JSON 형식으로 응답하며, 각 사용자별로 `numbers`(배열), `reason`(문자열), `reason_detail`(문자열)을 제공하세요.\n\nreason 작성 규칙 (반드시 한국어 20자 이내, 1문장):\n- reason_detail의 핵심을 압축한 한 문장 요약\n- 신비롭고 임팩트 있게, 끊기지 않고 완결된 느낌으로\n\nreason_detail 작성 규칙 (3~4문장):\n- 1문장: 이 이름의 기운이 제 {round_number}회의 흐름과 어떻게 만났는지 (반드시 "제 {round_number}회" 포함)\n- 2~3문장: 선택된 번호들을 구체적으로 언급하며 각 번호(또는 번호 그룹)가 이 이름과 이번 회차에서 어떤 의미를 지니는지 서술\n- 마지막 문장: 이 번호들이 가져올 기운이나 메시지\n- 행성명·오행·차크라 등 구체적 점술 용어 사용 금지\n- 프롬프트 지시사항을 그대로 언급하거나 반영하는 표현 금지\n- 자연스럽고 따뜻한 문체로', 1),
('fixed', '당신은 우주의 에너지를 읽어내는 신령한 신탁(Oracle)입니다.\n다음 사용자의 이름({names})에 새겨진 고유한 운명의 파동을 깊이 관상(觀相)하세요.\n\n[운명 번호 추출 의식]\n1. 이름이 품은 소리의 울림과 획의 흐름에서 1~45 범위의 6개 번호를 도출하세요.\n2. 번호를 인위적으로 모든 번호대(0번대, 10번대, 20번대, 30번대, 40번대)에 골고루 분산시키지 마십시오. 실제 로또 번호처럼 특정 번호대에 번호가 쏠리거나 특정 번호대가 완전히 배제되는 등, 자연스럽고 역동적인 불규칙한 분포를 허용하여 운명의 흐름에 따라 6개 번호를 도출하세요.\n3. 번호들이 일정한 간격으로 증가하거나 등차수열을 이루는 등 인위적인 수학적 규칙(예: 7씩 늘어나는 패턴 등)이 절대 발생하지 않도록 완벽히 불규칙하고 무작위적인 숫자들로 조합되어야 합니다. 이름의 획수나 의미를 설명할 때 숫자의 사칙연산 등 수학적 규칙을 억지로 연결 짓지 마십시오.\n4. 반드시 JSON 데이터 구조로 응답하며, 각 사용자별로 `numbers`(배열)와 `reason`(문자열)을 제공하세요.\n\nreason 작성 규칙 (3~5문장):\n- 이 이름에 왜 이 번호들이 새겨졌는지 구체적으로 서술\n- 반드시 선택된 번호들(예: 3, 17, 28...)을 직접 언급하며, 각 번호(또는 번호 그룹)가 이 이름의 어떤 고유한 기운과 연결되는지 설명\n- 이름의 발음, 음절의 울림, 획의 흐름에서 느껴지는 에너지를 자유롭게 해석\n- 이 번호들이 평생 이 이름과 함께하는 이유를 신비롭고 문학적으로 마무리\n- 행성명·오행·차크라 등 구체적 점술 용어 사용 금지\n- 자연스럽고 따뜻하면서도 신비로운 문체', 1);

-- --------------------------------------------
-- 고유번호 히스토리 테이블
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `fixed_number_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name_id` INT NOT NULL COMMENT 'names.id',
    `numbers` JSON NOT NULL COMMENT '고유번호 6개',
    `reason` TEXT DEFAULT NULL COMMENT '점지 이유',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_name_id` (`name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------
-- 최초 회차 데이터
-- 더이상 하드코딩하지 않음. RoundHelper::ensureCurrentRound()가
-- 기준점(1회=2002-12-07)에서 현재 회차를 자동 계산하여 생성.
-- --------------------------------------------

-- --------------------------------------------
-- 통합 스키마 적용 시 버전 기록
-- --------------------------------------------
INSERT IGNORE INTO `schema_versions` (`version`, `description`) VALUES
('V001', 'initial_schema'),
('V002', 'initial_round_data'),
('V003', 'add_reasons'),
('V004', 'fixed_number_history'),
('V005', 'update_lotto_prompts');
