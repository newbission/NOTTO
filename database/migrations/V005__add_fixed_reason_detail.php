<?php

/**
 * V005: names 테이블에 fixed_reason_detail 컬럼 추가
 *
 * $pdo 는 migrator.php 가 주입함
 * @var PDO $pdo
 */

$pdo->exec("
    ALTER TABLE `names`
    ADD COLUMN `fixed_reason_detail` TEXT DEFAULT NULL COMMENT '고유번호 번호별 상세 설명'
    AFTER `fixed_reason`
");
