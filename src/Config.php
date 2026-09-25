<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

final class Config
{
    public static function load(?string $file = null): array
    {
        $file ??= getenv('WISHLIST_CONFIG') ?: dirname(__DIR__) . '/config/config.php';
        if (!is_file($file)) {
            throw new RuntimeException("Fichier de configuration introuvable : $file (copier config/config.example.php)");
        }
        $config = require $file;
        if (!is_array($config)) {
            throw new RuntimeException("Configuration invalide : $file");
        }
        return self::normalize($config);
    }

    public static function normalize(array $config): array
    {
        if (!isset($config['db']) || !is_array($config['db'])) {
            throw new RuntimeException('Configuration invalide : section "db" manquante');
        }
        $config['app'] = ($config['app'] ?? []) + [
            'debug' => false,
            'timezone' => 'Europe/Paris',
            'session_name' => 'wishlist_sid',
            'cookie_secure' => true,
        ];
        $config['paths'] = ($config['paths'] ?? []) + ['storage' => dirname(__DIR__) . '/storage'];
        $config['backup'] = ($config['backup'] ?? []) + ['mysqldump' => 'mysqldump', 'keep' => 10];
        return $config;
    }
}
