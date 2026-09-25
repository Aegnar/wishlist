<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Db
{
    /** @param array{host: string, port?: int, name: string, user: string, pass: string} $db */
    public static function connect(array $db): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $db['host'],
            (int) ($db['port'] ?? 3306),
            $db['name'],
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Aligne NOW() de MariaDB sur le fuseau de PHP.
        $pdo->exec("SET time_zone = '" . (new \DateTimeImmutable())->format('P') . "'");
        return $pdo;
    }
}
