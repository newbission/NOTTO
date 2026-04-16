<?php

/**
 * V002: 최초 회차 데이터 (1212회)
 *
 * @var PDO $pdo
 */

$stmt = $pdo->prepare("INSERT IGNORE INTO `rounds` (`round_number`, `draw_date`) VALUES (?, ?)");
$stmt->execute([1212, '2026-02-21']);
