<?php
declare(strict_types=1);

use App\Schema\Migration;

return new class implements Migration {
    public function isDestructive(): bool
    {
        return false;
    }

    public function up(PDO $db): void
    {
        $options = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(50) NOT NULL,
                display_name VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin','user') NOT NULL DEFAULT 'user',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_username (username)
            ) $options
            SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(200) NOT NULL,
                description TEXT NULL,
                url VARCHAR(2048) NULL,
                store VARCHAR(100) NULL,
                image_path VARCHAR(255) NULL,
                price_estimated DECIMAL(10,2) NULL,
                quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                priority ENUM('high','none','low') NOT NULL DEFAULT 'none',
                is_purchased TINYINT(1) NOT NULL DEFAULT 0,
                purchased_at DATE NULL,
                price_paid DECIMAL(10,2) NULL,
                purchased_by INT UNSIGNED NULL,
                created_by INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_items_purchased_priority (is_purchased, priority),
                KEY idx_items_purchased_created (is_purchased, created_at),
                KEY idx_items_purchased_date (is_purchased, purchased_at),
                CONSTRAINT fk_items_created_by FOREIGN KEY (created_by) REFERENCES users (id),
                CONSTRAINT fk_items_purchased_by FOREIGN KEY (purchased_by) REFERENCES users (id) ON DELETE SET NULL
            ) $options
            SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS tags (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_tags_name (name)
            ) $options
            SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS item_tag (
                item_id INT UNSIGNED NOT NULL,
                tag_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (item_id, tag_id),
                KEY idx_item_tag_tag (tag_id),
                CONSTRAINT fk_item_tag_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
                CONSTRAINT fk_item_tag_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE
            ) $options
            SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS comments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                item_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_comments_item (item_id, created_at),
                CONSTRAINT fk_comments_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
                CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users (id)
            ) $options
            SQL);

        $db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip VARCHAR(45) NOT NULL,
                username VARCHAR(50) NOT NULL,
                attempted_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_login_attempts_ip (ip, attempted_at)
            ) $options
            SQL);
    }
};
