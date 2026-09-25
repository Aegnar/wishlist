<?php
declare(strict_types=1);

namespace App\Repository;

use App\Validator;
use InvalidArgumentException;
use PDO;
use Throwable;

final class TagRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tags WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Recherche insensible à la casse (collation utf8mb4_unicode_ci). */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tags WHERE name = ?');
        $stmt->execute([Validator::tagName($name)]);
        return $stmt->fetch() ?: null;
    }

    public function findOrCreate(string $name): int
    {
        $name = Validator::tagName($name);
        if ($name === '') {
            throw new InvalidArgumentException('Le nom du tag est vide.');
        }
        $existing = $this->findByName($name);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->db->prepare('INSERT INTO tags (name, created_at) VALUES (?, NOW())')->execute([$name]);
        return (int) $this->db->lastInsertId();
    }

    /** @param list<string> $names */
    public function syncItemTags(int $itemId, array $names): void
    {
        $ids = array_values(array_unique(array_map(fn (string $n): int => $this->findOrCreate($n), $names)));
        $this->db->prepare('DELETE FROM item_tag WHERE item_id = ?')->execute([$itemId]);
        $insert = $this->db->prepare('INSERT INTO item_tag (item_id, tag_id) VALUES (?, ?)');
        foreach ($ids as $tagId) {
            $insert->execute([$itemId, $tagId]);
        }
    }

    /**
     * @param list<int> $itemIds
     * @return array<int, list<string>>
     */
    public function namesForItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT it.item_id, t.name FROM item_tag it JOIN tags t ON t.id = it.tag_id
             WHERE it.item_id IN ($marks) ORDER BY t.name, it.item_id"
        );
        $stmt->execute(array_values(array_map('intval', $itemIds)));
        $out = [];
        foreach ($stmt as $row) {
            $out[(int) $row['item_id']][] = $row['name'];
        }
        return $out;
    }

    /** @return list<string> */
    public function suggest(string $q, int $limit = 10): array
    {
        $q = Validator::tagName($q);
        $escaped = addcslashes($q, '%_\\');
        $stmt = $this->db->prepare(
            'SELECT name FROM tags WHERE name LIKE ? ORDER BY (name LIKE ?) DESC, name LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute(['%' . $escaped . '%', $escaped . '%']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> */
    public function allNames(): array
    {
        return $this->db->query('SELECT name FROM tags ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<array{id: int, name: string, usage_count: int}> */
    public function allWithCounts(): array
    {
        return $this->db->query(
            'SELECT t.id, t.name, COUNT(it.item_id) AS usage_count
             FROM tags t LEFT JOIN item_tag it ON it.tag_id = t.id
             GROUP BY t.id, t.name ORDER BY t.name'
        )->fetchAll();
    }

    public function rename(int $id, string $name): void
    {
        $name = Validator::tagName($name);
        if ($name === '') {
            throw new InvalidArgumentException('Le nom du tag est vide.');
        }
        $existing = $this->findByName($name);
        if ($existing !== null && (int) $existing['id'] !== $id) {
            throw new InvalidArgumentException("Le tag « $name » existe déjà : utilisez la fusion.");
        }
        $this->db->prepare('UPDATE tags SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    /** Déplace les produits de $fromId vers $toId puis supprime $fromId. */
    public function merge(int $fromId, int $toId): void
    {
        if ($fromId === $toId) {
            throw new InvalidArgumentException('Impossible de fusionner un tag avec lui-même.');
        }
        if ($this->find($fromId) === null || $this->find($toId) === null) {
            throw new InvalidArgumentException('Tag introuvable.');
        }
        $this->db->beginTransaction();
        try {
            $this->db
                ->prepare('INSERT IGNORE INTO item_tag (item_id, tag_id) SELECT item_id, ? FROM item_tag WHERE tag_id = ?')
                ->execute([$toId, $fromId]);
            $this->db->prepare('DELETE FROM tags WHERE id = ?')->execute([$fromId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteIfUnused(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tags WHERE id = ? AND NOT EXISTS (SELECT 1 FROM item_tag WHERE tag_id = ?)');
        $stmt->execute([$id, $id]);
        return $stmt->rowCount() === 1;
    }
}
