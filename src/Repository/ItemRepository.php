<?php
declare(strict_types=1);

namespace App\Repository;

use App\ItemFilter;
use PDO;
use Throwable;

final class ItemRepository
{
    private const SELECT = 'SELECT i.*, cu.display_name AS created_by_name, pu.display_name AS purchased_by_name
        FROM items i
        JOIN users cu ON cu.id = i.created_by
        LEFT JOIN users pu ON pu.id = i.purchased_by';

    public function __construct(private PDO $db, private TagRepository $tags)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE i.id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if ($item === false) {
            return null;
        }
        $item['tags'] = $this->tags->namesForItems([$id])[$id] ?? [];
        return $item;
    }

    /** @return list<array> */
    public function search(ItemFilter $filter): array
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->db->prepare(self::SELECT . " WHERE $where ORDER BY " . $this->orderBy($filter));
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        $tags = $this->tags->namesForItems(array_column($items, 'id'));
        foreach ($items as &$item) {
            $item['tags'] = $tags[$item['id']] ?? [];
        }
        unset($item);
        return $items;
    }

    /** @return array{total: string, priced: int, unpriced: int} */
    public function totals(ItemFilter $filter): array
    {
        [$where, $params] = $this->where($filter);
        $amount = $filter->purchased ? 'i.price_paid' : 'i.price_estimated * i.quantity';
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM($amount), 0) AS total, COUNT($amount) AS priced, COUNT(*) - COUNT($amount) AS unpriced
             FROM items i WHERE $where"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [
            'total' => number_format((float) $row['total'], 2, '.', ''),
            'priced' => (int) $row['priced'],
            'unpriced' => (int) $row['unpriced'],
        ];
    }

    /** @return array{todo: int, purchased: int} */
    public function counts(): array
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(is_purchased = 0), 0) AS todo, COALESCE(SUM(is_purchased = 1), 0) AS purchased FROM items'
        )->fetch();
        return ['todo' => (int) $row['todo'], 'purchased' => (int) $row['purchased']];
    }

    public function create(array $data, int $userId): int
    {
        return $this->transaction(function () use ($data, $userId): int {
            $this->db->prepare(
                'INSERT INTO items (title, description, url, store, price_estimated, quantity, priority, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $data['title'], $data['description'], $data['url'], $data['store'],
                $data['price_estimated'], $data['quantity'], $data['priority'], $userId,
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->tags->syncItemTags($id, $data['tags']);
            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        $this->transaction(function () use ($id, $data): void {
            $this->db->prepare(
                'UPDATE items SET title = ?, description = ?, url = ?, store = ?, price_estimated = ?, quantity = ?, priority = ?, updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $data['title'], $data['description'], $data['url'], $data['store'],
                $data['price_estimated'], $data['quantity'], $data['priority'], $id,
            ]);
            $this->tags->syncItemTags($id, $data['tags']);
        });
    }

    public function setImage(int $id, ?string $name): void
    {
        $this->db->prepare('UPDATE items SET image_path = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $id]);
    }

    public function markPurchased(int $id, string $date, ?string $pricePaid, int $userId): void
    {
        $this->db->prepare(
            'UPDATE items SET is_purchased = 1, purchased_at = ?, price_paid = ?, purchased_by = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$date, $pricePaid, $userId, $id]);
    }

    public function unmarkPurchased(int $id): void
    {
        $this->db->prepare(
            'UPDATE items SET is_purchased = 0, purchased_at = NULL, price_paid = NULL, purchased_by = NULL, updated_at = NOW() WHERE id = ?'
        )->execute([$id]);
    }

    /** @return string|null nom de la photo à supprimer du disque */
    public function delete(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();
        if ($image === false) {
            return null;
        }
        $this->db->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
        return $image === null ? null : (string) $image;
    }

    /** @return array{0: string, 1: list<mixed>} clause WHERE et paramètres positionnels */
    private function where(ItemFilter $filter): array
    {
        $sql = ['i.is_purchased = ?'];
        $params = [$filter->purchased ? 1 : 0];
        if ($filter->q !== '') {
            $like = '%' . addcslashes($filter->q, '%_\\') . '%';
            $sql[] = '(i.title LIKE ? OR i.description LIKE ? OR i.store LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if ($filter->priority !== null) {
            $sql[] = 'i.priority = ?';
            $params[] = $filter->priority;
        }
        if ($filter->tags !== []) {
            $marks = implode(',', array_fill(0, count($filter->tags), '?'));
            $sql[] = "EXISTS (SELECT 1 FROM item_tag it JOIN tags t ON t.id = it.tag_id WHERE it.item_id = i.id AND t.name IN ($marks))";
            array_push($params, ...$filter->tags);
        }
        return [implode(' AND ', $sql), $params];
    }

    private function orderBy(ItemFilter $filter): string
    {
        $price = $filter->purchased ? 'i.price_paid' : 'i.price_estimated';
        return match ($filter->sort) {
            'created_desc' => 'i.created_at DESC, i.id DESC',
            'created_asc' => 'i.created_at ASC, i.id ASC',
            'price_asc' => "$price IS NULL, $price ASC, i.id DESC",
            'price_desc' => "$price IS NULL, $price DESC, i.id DESC",
            'purchased_desc' => 'i.purchased_at DESC, i.id DESC',
            'purchased_asc' => 'i.purchased_at ASC, i.id ASC',
            // ENUM('high','none','low') : l'ordre de déclaration donne haute → aucune → basse.
            default => 'i.priority ASC, i.created_at DESC, i.id DESC',
        };
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function transaction(callable $callback): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
