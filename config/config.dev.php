<?php
// Configuration de DÉVELOPPEMENT (Docker). Ne jamais réutiliser ces valeurs en production.
declare(strict_types=1);

return [
    'db' => [
        'host' => 'db',
        'port' => 3306,
        'name' => 'wishlist',
        'user' => 'wishlist_dev',
        'pass' => 'wishlist_dev',
    ],
    'app' => [
        'debug' => true,
        'timezone' => 'Europe/Paris',
        'session_name' => 'wishlist_dev_sid',
        'cookie_secure' => false,
    ],
    'backup' => [
        'mysqldump' => 'mysqldump',
        'keep' => 10,
    ],
];
