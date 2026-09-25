<?php
// Copier ce fichier en config/config.php puis renseigner les vraies valeurs.
// config/config.php n'est JAMAIS versionné (voir .gitignore).
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'wishlist',
        'user' => 'wishlist',
        'pass' => 'CHANGE_ME',
    ],
    'app' => [
        'debug' => false,
        'timezone' => 'Europe/Paris',
        'session_name' => 'wishlist_sid',
        'cookie_secure' => true, // false uniquement en développement sans HTTPS
    ],
    'backup' => [
        'mysqldump' => 'mysqldump',
        'keep' => 10,
    ],
];
