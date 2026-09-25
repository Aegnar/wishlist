<?php
// Schéma attendu de la base, utilisé par `php bin/db.php check`.
// Types au format normalisé de SchemaChecker::normalizeType() ; [type, nullable].
declare(strict_types=1);

return [
    'users' => [
        'columns' => [
            'id' => ['int unsigned', false],
            'username' => ['varchar(50)', false],
            'display_name' => ['varchar(100)', false],
            'password_hash' => ['varchar(255)', false],
            'role' => ["enum('admin','user')", false],
            'is_active' => ['tinyint', false],
            'created_at' => ['datetime', false],
            'last_login_at' => ['datetime', true],
        ],
        'indexes' => [
            'PRIMARY' => ['id'],
            'uq_users_username' => ['username'],
        ],
        'foreign_keys' => [],
    ],
    'items' => [
        'columns' => [
            'id' => ['int unsigned', false],
            'title' => ['varchar(200)', false],
            'description' => ['text', true],
            'url' => ['varchar(2048)', true],
            'store' => ['varchar(100)', true],
            'image_path' => ['varchar(255)', true],
            'price_estimated' => ['decimal(10,2)', true],
            'quantity' => ['smallint unsigned', false],
            'priority' => ["enum('high','none','low')", false],
            'is_purchased' => ['tinyint', false],
            'purchased_at' => ['date', true],
            'price_paid' => ['decimal(10,2)', true],
            'purchased_by' => ['int unsigned', true],
            'created_by' => ['int unsigned', false],
            'created_at' => ['datetime', false],
            'updated_at' => ['datetime', false],
        ],
        'indexes' => [
            'PRIMARY' => ['id'],
            'idx_items_purchased_priority' => ['is_purchased', 'priority'],
            'idx_items_purchased_created' => ['is_purchased', 'created_at'],
            'idx_items_purchased_date' => ['is_purchased', 'purchased_at'],
        ],
        'foreign_keys' => [
            'created_by' => 'users.id',
            'purchased_by' => 'users.id',
        ],
    ],
    'tags' => [
        'columns' => [
            'id' => ['int unsigned', false],
            'name' => ['varchar(50)', false],
            'created_at' => ['datetime', false],
        ],
        'indexes' => [
            'PRIMARY' => ['id'],
            'uq_tags_name' => ['name'],
        ],
        'foreign_keys' => [],
    ],
    'item_tag' => [
        'columns' => [
            'item_id' => ['int unsigned', false],
            'tag_id' => ['int unsigned', false],
        ],
        'indexes' => [
            'PRIMARY' => ['item_id', 'tag_id'],
            'idx_item_tag_tag' => ['tag_id'],
        ],
        'foreign_keys' => [
            'item_id' => 'items.id',
            'tag_id' => 'tags.id',
        ],
    ],
    'comments' => [
        'columns' => [
            'id' => ['int unsigned', false],
            'item_id' => ['int unsigned', false],
            'user_id' => ['int unsigned', false],
            'body' => ['text', false],
            'created_at' => ['datetime', false],
        ],
        'indexes' => [
            'PRIMARY' => ['id'],
            'idx_comments_item' => ['item_id', 'created_at'],
        ],
        'foreign_keys' => [
            'item_id' => 'items.id',
            'user_id' => 'users.id',
        ],
    ],
    'login_attempts' => [
        'columns' => [
            'id' => ['int unsigned', false],
            'ip' => ['varchar(45)', false],
            'username' => ['varchar(50)', false],
            'attempted_at' => ['datetime', false],
        ],
        'indexes' => [
            'PRIMARY' => ['id'],
            'idx_login_attempts_ip' => ['ip', 'attempted_at'],
        ],
        'foreign_keys' => [],
    ],
    'schema_migrations' => [
        'columns' => [
            'version' => ['varchar(50)', false],
            'checksum' => ['char(64)', false],
            'applied_at' => ['datetime', false],
        ],
        'indexes' => [
            'PRIMARY' => ['version'],
        ],
        'foreign_keys' => [],
    ],
];
