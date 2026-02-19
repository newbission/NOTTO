<?php

declare(strict_types=1);

/**
 * Prompt Model
 *
 * prompts 테이블 CRUD + 활성 프롬프트 전환
 */

require_once __DIR__ . '/../config/database.php';

class Prompt
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDatabase();
    }

    /**
     * 현재 활성 프롬프트 조회 (type별)
     */
    public function getActive(string $type): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM prompts WHERE type = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$type]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 전체 프롬프트 목록
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM prompts ORDER BY type, is_active DESC, created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * ID로 조회
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM prompts WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * 새 프롬프트 생성
     */
    public function create(string $type, string $content, bool $activate = false): array
    {
        if ($activate) {
            $this->deactivateAll($type);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO prompts (type, content, is_active) VALUES (?, ?, ?)"
        );
        $stmt->execute([$type, $content, $activate ? 1 : 0]);

        $id = (int) $this->pdo->lastInsertId();
        return $this->findById($id);
    }

    /**
     * 특정 프롬프트 활성화 (같은 type의 다른 프롬프트는 비활성화)
     */
    public function activate(int $id): bool
    {
        $prompt = $this->findById($id);
        if (!$prompt) {
            return false;
        }

        $this->deactivateAll($prompt['type']);

        $stmt = $this->pdo->prepare(
            "UPDATE prompts SET is_active = 1 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return true;
    }

    /**
     * 같은 type의 모든 프롬프트 비활성화
     */
    private function deactivateAll(string $type): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE prompts SET is_active = 0 WHERE type = ? AND is_active = 1"
        );
        $stmt->execute([$type]);
    }
}
