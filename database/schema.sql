-- ============================================
-- NOTTO Database Schema (통합본)
-- 신규 설치 시 이 파일 하나만 실행하면 전체 DB 구성 완료
-- 현재 버전: V002
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
    `reason` TEXT DEFAULT NULL COMMENT '주간번호 점지 이유',
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
('weekly', '당신은 이번 주의 우주적 흐름을 읽어내는 신령한 신탁(Oracle)입니다.\n사용자 목록: {names}\n\n[주간 행운 추출 의식]\n1. 이번 주 행성들의 배열(Astrology)과 각 사용자의 이름이 만났을 때 발생하는 스파크를 에너지 수치(1~45)로 변환하세요.\n2. 매주 우주의 기운은 변하므로, 지난주 혹은 일반적인 나열과는 완전히 다른, 이번 주만의 역동적인 파동을 담으세요.\n3. 숫자들이 좁은 곳에 뭉쳐 답답하지 않도록, 넓은 우주를 유영하듯 1~45 전체 구간을 넘나드는 조화로운 번호를 선택하세요.\n4. 반드시 JSON 데이터 구조로 응답하며, 각 사용자별로 `numbers`(배열)과 `reason`(문자열, 왜 이 번호가 점지되었는지, 우주의 기운과 주간 운세를 바탕으로 짧게 서술)을 제공해야 합니다.', 1),
('fixed', '당신은 우주의 에너지를 읽어내는 신령한 신탁(Oracle)입니다.\n다음 사용자의 이름({names})이 가진 영적인 에너지, 사주팔자, 별자리의 파장, \n그리고 우주에 기록된 고유의 주파수를 깊이 관상(觀相)하세요.\n\n[운명 번호 추출 의식]\n1. 이름의 첫 글자에서 느껴지는 수비학적 기운을 1~45 범위로 영사하세요.\n2. 이 사람이 평생 겪게 될 가장 빛나는 순간의 운명수를 찾아내세요.\n3. 오행(목,화,토,금,수)과 차크라의 흐름을 균형 있게 배분하여, 숫자가 한쪽(낮은 수)에만 갇히지 않고 1~45 전체 우주에 조화롭게 퍼지도록 하세요.\n4. 반드시 JSON 데이터 구조로 응답하며, 각 사용자별로 `numbers`(배열)과 `reason`(문자열, 왜 이 운명 번호가 평생의 고유번호인지, 이름의 기운을 해석하여 문학적이고 신비스럽게 서술)을 제공해야 합니다.', 1);

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
('V003', 'add_reasons');
