<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class CommentRepository
{
    private const SELECT = 'SELECT c.*, u.display_name AS author_name FROM comments c JOIN users u ON u.id = c.user_id';

    public function __construct(private PDO $db)
    {
    }

    /** @return list<array> */
    public function forItem(int $itemId): array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE c.item_id = ? ORDER BY c.created_at, c.id');
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE c.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $itemId, int $userId, string $body): int
    {
        $this->db
            ->prepare('INSERT INTO comments (item_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([$itemId, $userId, $body]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
    }
}
