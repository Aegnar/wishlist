<?php
declare(strict_types=1);

namespace Tests;

use App\Db;
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
}
