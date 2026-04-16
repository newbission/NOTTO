<?php

/**
 * V004: 고유번호 히스토리 테이블 추가
 *
 * @var PDO $pdo
 */

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `fixed_number_history` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `name_id` INT NOT NULL COMMENT 'names.id',
        `numbers` JSON NOT NULL COMMENT '고유번호 6개',
        `reason` TEXT DEFAULT NULL COMMENT '점지 이유',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_name_id` (`name_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
