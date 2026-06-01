<?php

declare(strict_types=1);

/**
 * Round Model
 *
 * rounds + name_rounds 테이블 CRUD
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

class Round
{
    private function pdo(): PDO
    {
        return getDatabase();
    }

    /**
     * 최신 회차 조회
     */
    public function getLatest(): ?array
    {
        $stmt = $this->pdo()->query(
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
        $stmt = $this->pdo()->prepare(
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
        $stmt = $this->pdo()->prepare("SELECT * FROM rounds WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 새 회차 생성
     */
    public function create(int $roundNumber, string $drawDate): array
    {
        // 같은 연결 핸들을 재사용해야 lastInsertId()가 올바른 값을 반환한다.
        // getDatabase()가 매 호출마다 SELECT 1 헬스체크를 실행하는데, INSERT와
        // lastInsertId() 사이에 SELECT가 끼면 mysql_insert_id()가 0으로 리셋된다.
        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO rounds (round_number, draw_date) VALUES (?, ?)"
        );
        $stmt->execute([$roundNumber, $drawDate]);

        $id = (int) $pdo->lastInsertId();
        logInfo('새 회차 생성', ['id' => $id, 'round_number' => $roundNumber, 'draw_date' => $drawDate], 'model');
        return $this->findById($id);
    }

    /**
     * 당첨번호 저장
     */
    public function setWinningNumbers(int $roundNumber, array $numbers, int $bonus): void
    {
        $stmt = $this->pdo()->prepare(
            "UPDATE rounds SET winning_numbers = ?, bonus_number = ? WHERE round_number = ?"
        );
        $stmt->execute([json_encode($numbers), $bonus, $roundNumber]);
        logInfo('당첨번호 저장', ['round' => $roundNumber, 'numbers' => $numbers, 'bonus' => $bonus], 'model');
    }

    /**
     * name_rounds에 번호 저장
     */
    public function saveNameNumbers(int $nameId, int $roundId, array $numbers, ?string $reason = null, ?string $reasonDetail = null): void
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO name_rounds (name_id, round_id, numbers, reason, reason_detail) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), reason = VALUES(reason), reason_detail = VALUES(reason_detail)"
        );
        $stmt->execute([$nameId, $roundId, json_encode($numbers), $reason, $reasonDetail]);
        logDebug('이름별 번호 저장', ['name_id' => $nameId, 'round_id' => $roundId, 'numbers' => $numbers], 'model');
    }

    /**
     * 해당 회차 모든 name_rounds의 matched_count 계산 및 업데이트
     */
    public function calculateMatches(int $roundId): int
    {
        $round = $this->findById($roundId);
        if (!$round || !$round['winning_numbers']) {
            return 0;
        }

        $winningNumbers = json_decode($round['winning_numbers'], true);

        $stmt = $this->pdo()->prepare(
            "SELECT id, numbers FROM name_rounds WHERE round_id = ?"
        );
        $stmt->execute([$roundId]);
        $nameRounds = $stmt->fetchAll();

        $updateStmt = $this->pdo()->prepare(
            "UPDATE name_rounds SET matched_count = ? WHERE id = ?"
        );

        $updated = 0;
        foreach ($nameRounds as $nr) {
            $nameNumbers = json_decode($nr['numbers'], true);
            $matched = count(array_intersect($nameNumbers, $winningNumbers));
            $updateStmt->execute([$matched, $nr['id']]);
            $updated++;
        }

        logInfo('적중 수 계산 완료', ['round_id' => $roundId, 'updated' => $updated], 'model');
        return $updated;
    }
}
