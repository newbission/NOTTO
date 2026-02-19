-- ============================================
-- NOTTO Database Schema
-- InfinityFree 호환 (FK 미지원, MyISAM 호환)
-- ============================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS `names` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL COMMENT '등록 이름 (UTF-8, 최대 20자)',
    `status` ENUM('pending','active','deleted') NOT NULL DEFAULT 'pending' COMMENT '상태',
    `fixed_numbers` JSON DEFAULT NULL COMMENT '고유번호 (6개, 1~45)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name` (`name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `name_rounds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name_id` INT NOT NULL COMMENT 'names.id',
    `round_id` INT NOT NULL COMMENT 'rounds.id',
    `numbers` JSON NOT NULL COMMENT 'AI 생성 번호 6개',
    `matched_count` TINYINT DEFAULT NULL COMMENT '적중 수',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_name_round` (`name_id`, `round_id`),
    INDEX `idx_name_id` (`name_id`),
    INDEX `idx_round_id` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ============================================
-- 초기 프롬프트 데이터 (기본값)
-- ============================================

INSERT INTO `prompts` (`type`, `content`, `is_active`) VALUES
('weekly', '다음 사용자들의 이름을 기반으로 각각 1부터 45 사이의 중복 없는 행운의 로또 번호 6개를 생성해주세요. 반드시 JSON 배열로 응답하세요. 사용자 목록: {names}', 1),
('fixed', '다음 사용자의 이름에서 느껴지는 기운, 획수, 의미를 종합적으로 분석하여 이 이름만의 고유한 운명의 번호 6개(1~45, 중복 없음)를 생성해주세요. 이 번호는 이 이름에 평생 부여되는 고유번호입니다. 반드시 JSON 배열로 응답하세요. 사용자 목록: {names}', 1);

-- ============================================
-- 테스트용 샘플 이름 데이터
-- ============================================

INSERT INTO `names` (`name`, `status`, `fixed_numbers`) VALUES
('김민준', 'active', '[3, 12, 17, 28, 33, 41]'),
('이서연', 'active', '[5, 14, 22, 31, 38, 44]'),
('박지호', 'active', '[1, 9, 18, 27, 36, 42]'),
('최수빈', 'active', '[7, 15, 23, 30, 37, 45]'),
('정하은', 'active', '[2, 11, 19, 26, 34, 43]'),
('홍길동', 'pending', NULL),
('강예린', 'pending', NULL);
