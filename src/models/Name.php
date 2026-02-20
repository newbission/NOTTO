<?php

declare(strict_types=1);

/**
 * Name Model
 *
 * names 테이블 CRUD (이 서비스에는 "사용자" 개념이 없고 "이름"만 존재)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/logger.php';

class Name
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * 이름으로 조회 (정확히 일치)
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM names WHERE name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch();

        logDebug('Name.findByName', ['name' => $name, 'found' => $result !== false], 'model');
        return $result ?: null;
    }

    /**
     * ID로 조회
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM names WHERE id = ?");
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
            "INSERT INTO names (name, status) VALUES (?, 'pending')"
        );
        $stmt->execute([$name]);

        $id = (int) $this->pdo->lastInsertId();
        logInfo("이름 등록", ['id' => $id, 'name' => $name, 'status' => 'pending'], 'model');
        return $this->findById($id);
    }

    /**
     * 이름 부분 검색 (LIKE %query%) — deleted 제외
     */
    public function search(string $query, int $offset, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT n.*, 
                    nr.numbers AS weekly_numbers,
                    nr.matched_count,
                    r.round_number,
                    r.winning_numbers,
                    r.bonus_number
             FROM names n
             LEFT JOIN (
                 SELECT nr2.name_id, nr2.numbers, nr2.matched_count, nr2.round_id
                 FROM name_rounds nr2
                 WHERE nr2.round_id = (SELECT MAX(id) FROM rounds)
             ) nr ON n.id = nr.name_id
             LEFT JOIN rounds r ON nr.round_id = r.id
             WHERE (n.name LIKE ? AND n.status = 'active') OR n.name = ?
             ORDER BY n.updated_at DESC, n.id DESC
             LIMIT ? OFFSET ?"
        );
        $likeQuery = '%' . $query . '%';
        $stmt->execute([$likeQuery, $query, $limit, $offset]);
        $results = $stmt->fetchAll();

        logInfo('이름 검색', ['query' => $query, 'results' => count($results)], 'model');
        return $results;
    }

    /**
     * 이름 부분 검색 총 건수
     */
    public function searchCount(string $query): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM names WHERE (name LIKE ? AND status = 'active') OR name = ?"
        );
        $stmt->execute(['%' . $query . '%', $query]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * 전체 목록 (정렬 + 페이지네이션) — deleted 제외
     */
    public function getAll(string $orderBy, int $offset, int $limit): array
    {
        $sql = "SELECT n.id, n.name, n.status, n.created_at, n.updated_at,
                       nr.numbers AS weekly_numbers,
                       nr.matched_count,
                       r.round_number
                FROM names n
                LEFT JOIN (
                    SELECT nr2.name_id, nr2.numbers, nr2.matched_count, nr2.round_id
                    FROM name_rounds nr2
                    WHERE nr2.round_id = (SELECT MAX(id) FROM rounds)
                ) nr ON n.id = nr.name_id
                LEFT JOIN rounds r ON nr.round_id = r.id
                WHERE n.status = 'active'
                ORDER BY $orderBy
                LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM names WHERE status = 'active'"
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * pending 상태 전체 조회
     */
    public function getPending(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM names WHERE status = 'pending' ORDER BY created_at ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * active 상태 전체 조회
     */
    public function getActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM names WHERE status = 'active' ORDER BY id ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * 고유번호 저장 + active 상태 전환
     */
    public function activateWithFixedNumbers(int $id, array $numbers): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE names SET fixed_numbers = ?, status = 'active' WHERE id = ?"
        );
        $stmt->execute([json_encode($numbers), $id]);
        logInfo('이름 활성화 + 고유번호 부여', ['id' => $id, 'numbers' => $numbers], 'model');
    }

    /**
     * 상태 변경
     */
    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE names SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        logInfo('이름 상태 변경', ['id' => $id, 'status' => $status], 'model');
    }

    /**
     * 고유번호 조회 (정확히 일치하는 이름)
     */
    public function getFixedNumbers(string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, fixed_numbers, status, created_at 
             FROM names WHERE name = ? AND status != 'deleted'"
        );
        $stmt->execute([$name]);
        $result = $stmt->fetch();

        logInfo('고유번호 조회', ['name' => $name, 'found' => $result !== false], 'model');
        return $result ?: null;
    }
}
