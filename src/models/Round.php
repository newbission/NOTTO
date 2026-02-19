<?php

declare(strict_types=1);

/**
 * Round Model
 *
 * rounds + user_rounds 테이블 CRUD
 */

require_once __DIR__ . '/../config/database.php';

class Round
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * 최신 회차 조회
     */
    public function getLatest(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM rounds ORDER BY round_number DESC LIMIT 1"
        );
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 회차 번호로 조회
     */
    public function findByRoundNumber(int $roundNumber): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM rounds WHERE round_number = ?"
        );
        $stmt->execute([$roundNumber]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * ID로 조회
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 새 회차 생성
     */
    public function create(int $roundNumber, string $drawDate): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rounds (round_number, draw_date) VALUES (?, ?)"
        );
        $stmt->execute([$roundNumber, $drawDate]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    /**
     * 당첨번호 저장
     */
    public function setWinningNumbers(int $roundNumber, array $numbers, int $bonus): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rounds SET winning_numbers = ?, bonus_number = ? WHERE round_number = ?"
        );
        $stmt->execute([json_encode($numbers), $bonus, $roundNumber]);
    }

    /**
     * user_rounds에 번호 저장
     */
    public function saveUserNumbers(int $userId, int $roundId, array $numbers): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO user_rounds (user_id, round_id, numbers) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE numbers = VALUES(numbers)"
        );
        $stmt->execute([$userId, $roundId, json_encode($numbers)]);
    }

    /**
     * 해당 회차 모든 user_rounds의 matched_count 계산 및 업데이트
     */
    public function calculateMatches(int $roundId): int
    {
        $round = $this->findById($roundId);
        if (!$round || !$round['winning_numbers']) {
            return 0;
        }

        $winningNumbers = json_decode($round['winning_numbers'], true);

        // 해당 회차의 모든 user_rounds 조회
        $stmt = $this->pdo->prepare(
            "SELECT id, numbers FROM user_rounds WHERE round_id = ?"
        );
        $stmt->execute([$roundId]);
        $userRounds = $stmt->fetchAll();

        $updateStmt = $this->pdo->prepare(
            "UPDATE user_rounds SET matched_count = ? WHERE id = ?"
        );

        $updated = 0;
        foreach ($userRounds as $ur) {
            $userNumbers = json_decode($ur['numbers'], true);
            $matched = count(array_intersect($userNumbers, $winningNumbers));
            $updateStmt->execute([$matched, $ur['id']]);
            $updated++;
        }

        return $updated;
    }

    /**
     * 특정 회차에 이미 번호가 생성된 사용자 ID 목록
     */
    public function getGeneratedUserIds(int $roundId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT user_id FROM user_rounds WHERE round_id = ?"
        );
        $stmt->execute([$roundId]);
        return array_column($stmt->fetchAll(), 'user_id');
    }
}
