#!/bin/bash
set -e
mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS wishlist_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON wishlist_test.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
