<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([trim($username)]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<array> */
    public function all(): array
    {
        return $this->db->query('SELECT * FROM users ORDER BY display_name, id')->fetchAll();
    }

    public function create(string $username, string $displayName, string $password, string $role = 'user'): int
    {
        $this->db
            ->prepare('INSERT INTO users (username, display_name, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
            ->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int) $this->db->lastInsertId();
    }

    public function updatePassword(int $id, string $password): void
    {
        $this->db
            ->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->prepare('UPDATE users SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
