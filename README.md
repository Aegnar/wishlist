# Wishlist familiale

Petite application web auto-hébergée pour gérer à plusieurs une **liste commune d'achats** du foyer :
du stylo au meuble, avec photo, prix, tags, priorité, commentaires et archivage des achats.

- PHP 8.3 sans framework ni dépendance d'exécution, MariaDB, JavaScript vanilla
- Connexion obligatoire ; seul l'administrateur crée les comptes
- Liste filtrable (recherche, tags, priorité), vue cartes ou tableau, totaux estimés / dépensés
- Photos par envoi de fichier ou par URL (téléchargées, redimensionnées et stockées localement)
- Installable sur mobile (PWA) pour consulter et commenter

## Installation sur un serveur (Debian / Ubuntu, nginx, sans Docker)

### 1. Prérequis

```bash
sudo apt install nginx mariadb-server mariadb-client git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd php8.3-curl php8.3-mbstring
```

(`fileinfo`, `exif` et `zlib` sont inclus dans `php8.3-common`. Sur Debian 12, PHP 8.3 s'installe via le dépôt packages.sury.org.)

Limites d'envoi de fichiers — créer `/etc/php/8.3/fpm/conf.d/99-wishlist.ini` :

```ini
upload_max_filesize = 10M
post_max_size = 12M
expose_php = Off
memory_limit = 256M   ; le traitement d'une photo 24 mégapixels pivotée culmine vers 212 Mo
display_errors = Off  ; ne jamais exposer d'erreurs PHP aux visiteurs en production
```

### 2. Code

```bash
sudo git clone https://github.com/Aegnar/wishlist.git /var/www/wishlist
cd /var/www/wishlist
```

### 3. Base de données

```bash
sudo mariadb
```

```sql
CREATE DATABASE wishlist CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wishlist'@'localhost' IDENTIFIED BY 'un-mot-de-passe-long-et-unique';
GRANT ALL PRIVILEGES ON wishlist.* TO 'wishlist'@'localhost';
FLUSH PRIVILEGES;
```

### 4. Configuration

```bash
sudo cp config/config.example.php config/config.php
sudo nano config/config.php          # renseigner db.pass (et le reste si besoin)
sudo chown root:www-data config/config.php
sudo chmod 0640 config/config.php
sudo chown -R www-data:www-data storage
```

`config/config.php` n'est jamais versionné.

### 5. Installation de la base et du compte administrateur

```bash
sudo -u www-data php bin/db.php install
```

La commande crée les tables, demande l'identifiant, le nom et le mot de passe de l'administrateur, puis vérifie l'intégrité.
Les autres comptes se créent ensuite depuis la page **Admin** de l'application.

### 6. nginx et HTTPS

```bash
sudo cp deploy/nginx.conf.example /etc/nginx/sites-available/wishlist
sudo nano /etc/nginx/sites-available/wishlist     # remplacer wishlist.example.com
sudo ln -s /etc/nginx/sites-available/wishlist /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx php8.3-fpm
sudo certbot --nginx -d votre-domaine
```

## Mise à jour

```bash
cd /var/www/wishlist
sudo git pull
sudo -u www-data php bin/db.php upgrade
```

`upgrade` sauvegarde automatiquement la base (dans `storage/backups/`, 10 dernières conservées) **avant**
d'appliquer les migrations, puis vérifie l'intégrité. Si la base n'est pas à jour, l'application affiche
une page de maintenance au lieu de fonctionner avec un schéma incohérent.

Autres commandes :

| Commande | Rôle |
|---|---|
| `php bin/db.php status` | Version de la base, migrations en attente |
| `php bin/db.php check` | Vérifie tables, colonnes, index, clés étrangères et cohérence des données (ne modifie rien) |
| `php bin/db.php backup` | Sauvegarde manuelle |

## Sauvegardes

- Base : sauvegarde quotidienne conseillée, par exemple `/etc/cron.d/wishlist` :
  ```
  15 3 * * * www-data cd /var/www/wishlist && php bin/db.php backup > /dev/null
  ```
- Photos : le dossier `storage/uploads/` n'est **pas** dans les dumps SQL ; l'inclure dans la sauvegarde du serveur
  (avec `storage/backups/` et `config/config.php`).

## Développement (Docker, poste de dev uniquement)

```bash
docker compose -p wishlist-dev -f docker-compose.dev.yml up -d --build
docker compose -p wishlist-dev -f docker-compose.dev.yml exec -T php composer install
docker compose -p wishlist-dev -f docker-compose.dev.yml exec php php bin/db.php install
```

Application : http://127.0.0.1:8045 — MariaDB : 127.0.0.1:3345.

Tests (base `wishlist_test` dédiée) :

```bash
docker compose -p wishlist-dev -f docker-compose.dev.yml exec -T php vendor/bin/phpunit
```

Nouvelle évolution du schéma : créer `migrations/NNN_description.php` (ne jamais modifier une migration déjà
appliquée) **et** mettre à jour `schema.php` ; le test `SchemaCheckerTest` vérifie que les deux concordent.

## Structure

```
public/        racine web (index.php, assets, sw.js)
src/           code applicatif (App\…)
templates/     vues PHP
migrations/    migrations versionnées
schema.php     schéma attendu (vérification d'intégrité)
bin/db.php     gestion de la base
storage/       photos, sauvegardes, sessions, logs (non versionné)
deploy/        exemple de configuration nginx
```
