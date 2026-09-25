<?php
declare(strict_types=1);

namespace Tests;

use App\Db;
use App\Schema\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $pdo = null;

    /** @return array{host: string, port: int, name: string, user: string, pass: string} */
    protected static function dbConfig(): array
    {
        return [
            'host' => getenv('TEST_DB_HOST') ?: 'db',
            'port' => (int) (getenv('TEST_DB_PORT') ?: 3306),
            'name' => getenv('TEST_DB_NAME') ?: 'wishlist_test',
            'user' => getenv('TEST_DB_USER') ?: 'wishlist_dev',
            'pass' => getenv('TEST_DB_PASS') ?: 'wishlist_dev',
        ];
    }

    protected static function pdo(): PDO
    {
        return self::$pdo ??= Db::connect(self::dbConfig());
    }

    protected static function dropAllTables(): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Recrée toutes les tables à partir des migrations. */
    protected static function migrateFresh(): void
    {
        self::dropAllTables();
        (new Migrator(self::pdo(), dirname(__DIR__) . '/migrations'))->migrate();
    }

    /** Vide les tables de données (garde le schéma et schema_migrations). */
    protected static function truncateData(): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['comments', 'item_tag', 'tags', 'items', 'login_attempts', 'users'] as $table) {
            $pdo->exec("TRUNCATE TABLE $table");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected static function insertUser(string $username = 'alice', string $role = 'user', string $password = 'motdepasse-solide'): int
    {
        self::pdo()
            ->prepare('INSERT INTO users (username, display_name, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
            ->execute([$username, ucfirst($username), password_hash($password, PASSWORD_DEFAULT), $role]);
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $fields colonnes de items à surcharger */
    protected static function insertItem(int $userId, array $fields = []): int
    {
        $now = date('Y-m-d H:i:s');
        $fields += ['title' => 'Objet', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
        $columns = implode(', ', array_keys($fields));
        $marks = implode(', ', array_fill(0, count($fields), '?'));
        self::pdo()->prepare("INSERT INTO items ($columns) VALUES ($marks)")->execute(array_values($fields));
        return (int) self::pdo()->lastInsertId();
    }
}
