<?php

declare(strict_types=1);

/**
 * User Model
 *
 * users 테이블 CRUD
 */

require_once __DIR__ . '/../config/database.php';

class User
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * 이름으로 사용자 조회 (정확히 일치)
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * ID로 사용자 조회
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 새 이름 등록 (pending 상태)
     */
    public function create(string $name): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, status) VALUES (?, 'pending')"
        );
        $stmt->execute([$name]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    /**
     * 이름 부분 검색 (LIKE %query%) — deleted 제외
     */
    public function search(string $query, int $offset, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.*, 
                    ur.numbers AS weekly_numbers,
                    ur.matched_count,
                    r.round_number,
                    r.winning_numbers,
                    r.bonus_number
             FROM users u
             LEFT JOIN (
                 SELECT ur2.user_id, ur2.numbers, ur2.matched_count, ur2.round_id
                 FROM user_rounds ur2
                 INNER JOIN (
                     SELECT MAX(id) AS max_id FROM rounds
                 ) latest ON ur2.round_id = (SELECT MAX(id) FROM rounds)
             ) ur ON u.id = ur.user_id
             LEFT JOIN rounds r ON ur.round_id = r.id
             WHERE u.name LIKE ? AND u.status != 'deleted'
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * 이름 부분 검색 총 건수
     */
    public function searchCount(string $query): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE name LIKE ? AND status != 'deleted'"
        );
        $stmt->execute(['%' . $query . '%']);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 전체 목록 (정렬 + 페이지네이션) — deleted 제외
     */
    public function getAll(string $orderBy, int $offset, int $limit): array
    {
        $sql = "SELECT u.id, u.name, u.status, u.created_at,
                       ur.numbers AS weekly_numbers,
                       ur.matched_count,
                       r.round_number
                FROM users u
                LEFT JOIN (
                    SELECT ur2.user_id, ur2.numbers, ur2.matched_count, ur2.round_id
                    FROM user_rounds ur2
                    WHERE ur2.round_id = (SELECT MAX(id) FROM rounds)
                ) ur ON u.id = ur.user_id
                LEFT JOIN rounds r ON ur.round_id = r.id
                WHERE u.status != 'deleted'
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * 전체 건수 (deleted 제외)
     */
    public function countAll(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE status != 'deleted'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * pending 상태 사용자 전체 조회
     */
    public function getPending(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * active 상태 사용자 전체 조회
     */
    public function getActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM users WHERE status = 'active' ORDER BY id ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * 고유번호 저장 + active 상태 전환
     */
    public function activateWithFixedNumbers(int $id, array $numbers): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET fixed_numbers = ?, status = 'active' WHERE id = ?"
        );
        $stmt->execute([json_encode($numbers), $id]);
    }

    /**
     * 상태 변경
     */
    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    /**
     * 고유번호 조회 (정확히 일치하는 이름)
     */
    public function getFixedNumbers(string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, fixed_numbers, status, created_at 
             FROM users WHERE name = ? AND status != 'deleted'"
        );
        $stmt->execute([$name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
