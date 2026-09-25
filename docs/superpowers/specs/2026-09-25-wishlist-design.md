# Wishlist familiale — Spécification de conception

- **Date :** 2026-09-25
- **Statut :** validé en brainstorming, en attente de relecture
- **Dépôt :** public (GitHub) — aucune donnée sensible ne doit y être poussée

## 1. Objectif

Outil web simple, auto-hébergé, pour gérer à deux (plus un compte admin) une **liste commune d'achats pour le foyer** : du simple stylo au meuble pour l'appartement. On y enregistre les produits envisagés, on les priorise, on les commente, puis on les marque « achetés » (archivés mais consultables).

Références d'inspiration : [wishthis](https://github.com/wishthis/wishthis), [cmintey/wishlist](https://github.com/cmintey/wishlist). Contrairement à ces outils (listes de cadeaux partagées), ici il s'agit d'**une liste unique commune** de gestion d'achats.

### Hors périmètre V1

- Plusieurs listes, listes personnelles, partage public
- Inscription libre (seul l'admin crée les comptes)
- Récupération automatique titre/prix/image depuis l'URL produit (scraping)
- Mode hors-ligne de la PWA
- Notifications (mail, push)

## 2. Contraintes techniques

| Élément | Choix |
|---|---|
| Langages | PHP 8.3, SQL, JavaScript vanilla, HTML/CSS |
| Base de données | MariaDB (ou MySQL), `utf8mb4` / `utf8mb4_unicode_ci` |
| Serveur prod | VPS OVH, nginx + PHP-FPM 8.3, installation directe dans `/var/www/wishlist` — **pas de Docker en production** |
| Extensions PHP | `pdo_mysql`, `gd`, `curl`, `mbstring`, `fileinfo` |
| Dépendances runtime | **aucune** (pas de Composer en prod) |
| Dépendances dev | Composer + PHPUnit uniquement |
| Environnement de dev | Docker Desktop, projet compose isolé `wishlist-dev` (voir §10) |

## 3. Architecture

Application PHP **rendue côté serveur** (multi-pages), avec un routeur maison à point d'entrée unique et du JavaScript léger uniquement là où il apporte du confort (autocomplétion des tags, commentaires sans rechargement, aperçu photo, modale d'achat, bascule cartes/tableau).

### Arborescence

```
/var/www/wishlist/
├── public/                    ← racine nginx (seul dossier exposé)
│   ├── index.php              ← front controller / routeur
│   ├── sw.js                  ← service worker minimal (page "hors connexion")
│   └── assets/
│       ├── css/app.css
│       ├── js/app.js          ← + modules : tags.js, comments.js, purchase.js
│       ├── icons/             ← icônes PWA
│       └── manifest.webmanifest
├── src/
│   ├── bootstrap.php          ← chargement config, autoload, session, gestion erreurs
│   ├── Router.php
│   ├── Db.php                 ← connexion PDO
│   ├── Auth.php               ← login, logout, rôle, rate limiting
│   ├── Csrf.php
│   ├── View.php               ← rendu templates + helper e()
│   ├── Validator.php
│   ├── ImageStore.php         ← upload, téléchargement URL, redimensionnement GD
│   ├── UrlGuard.php           ← anti-SSRF pour les URL d'images
│   ├── Repository/
│   │   ├── UserRepository.php
│   │   ├── ItemRepository.php ← CRUD, filtres, tri, totaux
│   │   ├── TagRepository.php
│   │   └── CommentRepository.php
│   ├── Controller/
│   │   ├── AuthController.php
│   │   ├── ItemController.php
│   │   ├── CommentController.php
│   │   ├── TagController.php  ← endpoint autocomplétion JSON
│   │   ├── MediaController.php← sert les photos après contrôle de session
│   │   ├── AccountController.php
│   │   └── AdminController.php
│   └── Schema/
│       ├── Migrator.php       ← application des migrations
│       ├── SchemaChecker.php  ← vérification d'intégrité
│       └── Backup.php         ← mysqldump avant upgrade
├── templates/                 ← vues PHP (layout, login, items/*, admin/*, errors/*)
├── migrations/                ← 001_init.php, 002_….php
├── schema.php                 ← description du schéma attendu (pour `check`)
├── bin/db.php                 ← CLI install/upgrade/check/status
├── config/
│   ├── config.example.php     ← versionné, valeurs fictives
│   └── config.php             ← NON versionné
├── storage/                   ← NON versionné (sauf .gitkeep)
│   ├── uploads/
│   ├── backups/
│   ├── sessions/              ← sessions PHP (dossier dédié, hors nettoyage système)
│   └── logs/
├── tests/                     ← PHPUnit
├── deploy/
│   └── nginx.conf.example
├── docker/                    ← dev uniquement
├── docker-compose.dev.yml     ← dev uniquement
└── README.md
```

Chaque unité a un rôle unique : les contrôleurs orchestrent (lecture requête → validation → repository → vue), les repositories sont les seuls à exécuter du SQL, `ImageStore`/`UrlGuard` isolent la gestion de fichiers et le réseau, `Schema/*` isole la gestion de la base.

### Routes

| Méthode | Route | Rôle | Description |
|---|---|---|---|
| GET/POST | `/login` | public | Connexion |
| POST | `/logout` | connecté | Déconnexion |
| GET | `/` | connecté | Liste « À acheter » (filtres en query string) |
| GET | `/purchased` | connecté | Liste « Achetés » |
| GET | `/item/new` · POST `/item` | connecté | Création |
| GET | `/item/{id}` | connecté | Fiche produit + commentaires |
| GET | `/item/{id}/edit` · POST `/item/{id}` | connecté | Modification |
| POST | `/item/{id}/purchase` | connecté | Marquer acheté (date, prix payé) |
| POST | `/item/{id}/unpurchase` | connecté | Désarchiver |
| POST | `/item/{id}/delete` | connecté | Suppression définitive |
| POST | `/item/{id}/comments` | connecté | Ajout commentaire (JSON ou formulaire) |
| POST | `/comments/{id}/delete` | auteur ou admin | Suppression commentaire |
| GET | `/tags/suggest?q=` | connecté | Autocomplétion (JSON) |
| GET | `/media/{file}` | connecté | Sert une photo / miniature |
| GET/POST | `/account` | connecté | Changer son mot de passe |
| GET | `/admin` | admin | Tableau de bord admin |
| GET/POST | `/admin/users…` | admin | Créer, réinitialiser mdp, activer/désactiver |
| GET/POST | `/admin/tags…` | admin | Renommer, fusionner, supprimer |

Toute route autre que `/login` redirige vers `/login` si non connecté. Toute action modifiante est en POST avec jeton CSRF.

## 4. Modèle de données

### `users`

| Colonne | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| username | VARCHAR(50) UNIQUE | identifiant de connexion |
| display_name | VARCHAR(100) | nom affiché |
| password_hash | VARCHAR(255) | `password_hash()` (bcrypt par défaut) |
| role | ENUM('admin','user') | défaut `user` |
| is_active | TINYINT(1) | défaut 1 ; compte désactivé = connexion refusée |
| created_at | DATETIME | |
| last_login_at | DATETIME NULL | |

### `items`

| Colonne | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| title | VARCHAR(200) | **obligatoire** |
| description | TEXT NULL | |
| url | VARCHAR(2048) NULL | lien produit, http(s) uniquement |
| store | VARCHAR(100) NULL | magasin / enseigne, texte libre |
| image_path | VARCHAR(255) NULL | nom de fichier aléatoire dans `storage/uploads/` |
| price_estimated | DECIMAL(10,2) NULL | prix unitaire estimé, en € |
| quantity | SMALLINT UNSIGNED | défaut 1, min 1 |
| priority | ENUM('high','none','low') | défaut `none` |
| is_purchased | TINYINT(1) | défaut 0 |
| purchased_at | DATE NULL | obligatoire si `is_purchased = 1` |
| price_paid | DECIMAL(10,2) NULL | prix total réellement payé |
| purchased_by | INT UNSIGNED NULL FK users | |
| created_by | INT UNSIGNED FK users | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

Index : `(is_purchased, priority)`, `(is_purchased, created_at)`, `(is_purchased, purchased_at)`.

### `tags` et `item_tag`

- `tags` : `id`, `name` VARCHAR(50) UNIQUE (collation insensible à la casse), `created_at`.
- `item_tag` : `item_id` FK items ON DELETE CASCADE, `tag_id` FK tags ON DELETE CASCADE, PK `(item_id, tag_id)`.

### `comments`

`id`, `item_id` FK items ON DELETE CASCADE, `user_id` FK users, `body` TEXT (max 2000 caractères), `created_at`.

### `login_attempts`

`id`, `ip` VARCHAR(45), `username` VARCHAR(50), `attempted_at` DATETIME, index `(ip, attempted_at)`. Purge des entrées > 24 h à chaque tentative.

### `schema_migrations`

`version` VARCHAR(50) PK, `checksum` CHAR(64) (sha256 du fichier), `applied_at` DATETIME.

### Règles métier

- **Liste commune** : tout utilisateur connecté voit et modifie tous les produits. L'auteur (`created_by`) et l'acheteur (`purchased_by`) sont tracés.
- **Ordre de priorité** : haute → aucune → basse (`low` = « plus tard »). Tri par défaut de l'onglet À acheter : priorité, puis date d'ajout décroissante.
- **Marquer acheté** : modale demandant la date (défaut : aujourd'hui) et le prix payé (prérempli : `price_estimated × quantity` si connu). Enregistre `is_purchased=1`, `purchased_at`, `price_paid`, `purchased_by`.
- **Désarchiver** : remet `is_purchased=0` et vide `purchased_at`, `price_paid`, `purchased_by`.
- **Suppression** : définitive après confirmation ; supprime les commentaires et liaisons tags (cascade) et les fichiers photo.
- **Tags** : saisie libre ; un tag inconnu est créé à la volée ; normalisation (trim, espaces multiples réduits, 50 caractères max). Un tag n'est jamais supprimé automatiquement ; l'admin peut supprimer ceux inutilisés.
- **Totaux** : À acheter = Σ(`price_estimated × quantity`) des produits filtrés ayant un prix ; Achetés = Σ(`price_paid`) des produits filtrés. Le nombre de produits sans prix est affiché à côté du total.
- **Commentaires** : ajout par tout utilisateur connecté, y compris sur un produit acheté ; suppression par l'auteur ou l'admin ; pas d'édition en V1.

## 5. Écrans

### Connexion (`/login`)

Page d'accueil = uniquement identifiant + mot de passe + case « rester connecté ». Aucun lien d'inscription. Message d'erreur générique (« identifiants invalides ») sans préciser si l'utilisateur existe.

### Liste (`/` et `/purchased`) — priorité PC

- Onglets **À acheter** / **Achetés** avec compteurs.
- Barre de filtres : recherche texte (titre, description, magasin), tags (multi-sélection, logique « au moins un »), priorité, tri (priorité, prix, date d'ajout ; + date d'achat sur Achetés). Filtres côté serveur, reflétés dans l'URL.
- Deux vues, choix mémorisé en `localStorage` :
  - **Cartes** : miniature, titre, prix × quantité, magasin, tags, badge priorité.
  - **Tableau** : dense, colonnes triables.
  - *Écart accepté en V1* : le tri se choisit dans une liste déroulante de la barre de filtres (pas d'en-têtes de colonnes cliquables).
- Totaux du filtre actif en en-tête.
- Bouton « + Ajouter un produit ».

### Fiche produit (`/item/{id}`)

Photo en grand, tous les champs, lien « Voir le produit ↗ » (`rel="noopener noreferrer"`, nouvel onglet), historique (ajouté par / le, acheté par / le / prix payé). Fil de commentaires (auteur, date relative, texte) avec ajout sans rechargement. *Écart accepté en V1* : dates des commentaires affichées en absolu (« 25/09/2026 à 14:30 »), pas en relatif. Actions : Modifier, Marquer acheté / Désarchiver, Supprimer.

### Formulaire (`/item/new`, `/item/{id}/edit`)

Champs : titre*, URL produit, magasin, prix estimé, quantité, priorité (radio), description, tags (pastilles + autocomplétion), photo.

**Photo** — deux méthodes, une seule photo par produit :
1. **Upload** (sélection ou glisser-déposer) : max 8 Mo.
2. **URL d'image** : téléchargement côté serveur (voir §6, `UrlGuard`) : max 5 Mo, timeout 10 s, 3 redirections max. *Écart accepté en V1* : délai total de 15 s pour l'ensemble du téléchargement, redirections comprises (établissement de chaque connexion : 5 s max).

Traitement commun (`ImageStore`) : vérification du type réel (`finfo`, jpeg/png/webp/gif), décodage GD, redimensionnement (1600 px max + miniature 400 px), réencodage (WebP ou JPEG qualité 85) — supprime EXIF et neutralise les fichiers piégés. Nom de fichier aléatoire (`bin2hex(random_bytes(16))`). En modification : possibilité de remplacer ou retirer la photo ; l'ancien fichier est supprimé.

En cas d'erreur de validation, le formulaire est réaffiché avec les valeurs saisies et les erreurs sous chaque champ.

### Admin (`/admin`)

- **Utilisateurs** : liste, créer (identifiant, nom affiché, mot de passe provisoire ≥ 10 caractères, rôle), réinitialiser un mot de passe, activer/désactiver. L'admin ne peut pas se désactiver lui-même ni retirer le dernier admin.
- **Tags** : liste avec nombre d'utilisations, renommer, fusionner A → B (les produits de A reçoivent B, A est supprimé), supprimer un tag inutilisé.

### Mon compte (`/account`)

Changement de mot de passe (ancien + nouveau ×2, ≥ 10 caractères).

### Webapp mobile (PWA)

- `manifest.webmanifest` (nom, icônes 192/512, `display: standalone`, couleurs), installable via « Ajouter à l'écran d'accueil ».
- Mise en page responsive : sur petit écran, liste en cartes une colonne, fiche optimisée lecture + commentaires ; le formulaire d'ajout reste utilisable.
- `sw.js` minimal : met en cache uniquement une page « Pas de connexion » et l'affiche en cas d'échec réseau. Aucune donnée métier en cache.

## 6. Sécurité

### Dépôt public

`.gitignore` :

```
/config/config.php
/storage/uploads/*
/storage/backups/*
/storage/logs/*
/storage/sessions/*
!/storage/**/.gitkeep
*.sql
*.sql.gz
.env*
/vendor/
/.phpunit.cache/
/.idea/
/.vscode/
.DS_Store
Thumbs.db
/.remember/
/docker/data/
```

- Seul `config/config.example.php` est versionné (valeurs fictives).
- Aucun identifiant, mot de passe, nom de domaine ou IP réel dans le code, la doc, les tests ou les messages de commit. Le compte admin est créé interactivement par `bin/db.php install`.
- Les identifiants de l'environnement Docker de dev sont des valeurs de dev évidentes (`wishlist_dev`), jamais réutilisées en prod.
- Contrôle de `git diff --cached` avant chaque commit.

### Application

- **Sessions** : cookie `HttpOnly`, `Secure` (désactivable en dev via config), `SameSite=Lax`, nom de session dédié ; `session_regenerate_id(true)` à la connexion ; « rester connecté » = cookie prolongé jusqu'à 30 jours d'inactivité, sinon cookie de session navigateur et déconnexion après 24 h d'inactivité ; sessions stockées dans `storage/sessions/`. Vérification à chaque requête que l'utilisateur existe toujours et est actif, et que son mot de passe n'a pas changé depuis l'ouverture de la session (empreinte du hash en session) : un changement de mot de passe déconnecte les autres appareils. *Précision V1* : une session n'est démarrée que si la requête porte déjà le cookie de session ou vise `/login` (un visiteur anonyme redirigé ne crée aucun fichier de session).
- **Rate limiting** : 5 échecs par IP en 15 minutes → connexion bloquée 15 minutes pour cette IP (message générique). *Précision V1* : en IPv6 le compteur porte sur le préfixe /64 ; plafond supplémentaire de 20 échecs en 15 minutes par identifiant (insensible à la casse), toutes IP confondues.
- **CSRF** : jeton par session, champ caché dans chaque formulaire, en-tête `X-CSRF-Token` pour les appels JS ; vérifié sur toute requête POST.
- **SQL** : requêtes préparées PDO exclusivement (`PDO::ATTR_EMULATE_PREPARES = false`, `ERRMODE_EXCEPTION`).
- **XSS** : toute sortie échappée via `e()` (`htmlspecialchars`, `ENT_QUOTES`, UTF-8) ; les commentaires et descriptions sont affichés en texte brut (retours à la ligne conservés).
- **Autorisations** : rôle admin vérifié côté serveur dans chaque action admin.
- **URL produit** : seuls les schémas `http`/`https` sont acceptés (empêche `javascript:`).
- **Anti-SSRF (`UrlGuard`)** : pour le téléchargement d'image : schéma http/https uniquement, résolution DNS puis refus des IP privées, loopback, link-local, réservées (IPv4 et IPv6) ; connexion forcée sur l'IP vérifiée (`CURLOPT_RESOLVE`) ; revérification à chaque redirection ; limite de taille appliquée pendant le téléchargement.
- **Photos** : stockées hors de `public/`, servies par `MediaController` après contrôle de session, avec `Content-Type` fixé et `X-Content-Type-Options: nosniff`. Le nom demandé est validé par regex (hex + extension) pour empêcher toute traversée de chemin.
- **En-têtes** : `Content-Security-Policy` (`default-src 'self'`, `img-src 'self' data: blob:` pour l'aperçu photo, ni script ni style inline), `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `X-Frame-Options: DENY`.

## 7. Gestion de la base : installation, mises à jour, intégrité

Script CLI unique `bin/db.php` (refuse de s'exécuter hors CLI) :

| Commande | Effet |
|---|---|
| `install` | Refuse si des tables existent déjà. Applique toutes les migrations, puis demande interactivement identifiant, nom affiché et mot de passe (saisie masquée, confirmation) du compte admin. |
| `upgrade` | 1) vérifie les checksums des migrations déjà appliquées ; 2) s'il y a des migrations en attente : sauvegarde `mysqldump` ; 3) applique les migrations en attente, dans l'ordre ; 4) lance `check`. |
| `check` | Vérifie l'intégrité sans rien modifier ; code de sortie ≠ 0 si anomalie. |
| `status` | Version actuelle, migrations appliquées et en attente. |
| `backup` | Sauvegarde manuelle. |

### Migrations

- Fichiers `migrations/NNN_description.php` retournant un objet avec `up(PDO $db): void` et un drapeau `destructive` (défaut `false`).
- Une migration appliquée est enregistrée dans `schema_migrations` avec le sha256 de son fichier. Si un fichier appliqué a été modifié depuis (checksum différent), `upgrade` s'arrête avec une erreur explicite : on ne modifie jamais une migration déjà appliquée, on en crée une nouvelle.
- **Additif par défaut** : une migration marquée `destructive: true` (DROP, changement de type avec perte possible) exige une confirmation interactive (ou l'option `--allow-destructive`).
- MariaDB n'offre pas de DDL transactionnel : une migration interrompue peut laisser un état partiel. D'où la sauvegarde préalable obligatoire et l'enregistrement de la version **après** succès complet ; les migrations sont écrites pour être rejouables quand c'est possible (`CREATE TABLE IF NOT EXISTS`, vérification d'existence de colonne avant `ADD COLUMN`).

### Sauvegarde

- `mysqldump --single-transaction --routines` compressé en gzip vers `storage/backups/YYYYMMDD-HHMMSS.sql.gz`. Les identifiants sont passés via un fichier d'options temporaire (`--defaults-extra-file`, droits 0600, supprimé après) — jamais en ligne de commande.
- Rotation : conservation des 10 dernières sauvegardes.
- Si la sauvegarde échoue (outil absent, erreur), `upgrade` s'arrête sans rien modifier.
- Les photos (`storage/uploads/`) ne sont pas incluses : le README recommande de les inclure dans la sauvegarde du VPS.

### Vérification d'intégrité (`check`)

- `schema.php` décrit le schéma attendu : tables, colonnes (type, nullabilité — les valeurs par défaut ne sont pas comparées, leur format varie selon les versions de MariaDB), index, clés étrangères.
- `SchemaChecker` compare avec `information_schema` et liste : tables manquantes, colonnes manquantes ou de type différent, index/FK manquants, tables inconnues (avertissement seulement).
- Contrôles de cohérence : produits achetés sans `purchased_at`, `image_path` pointant vers un fichier absent, fichiers dans `uploads/` non référencés (orphelins, signalés, jamais supprimés automatiquement), migrations appliquées absentes du disque.
- Un test automatisé vérifie que la base obtenue en appliquant toutes les migrations correspond exactement à `schema.php` (garde-fou contre l'oubli de mise à jour de l'un ou l'autre).

### Côté application web

L'application ne modifie jamais le schéma. Au démarrage, elle compare la dernière version de `schema_migrations` avec la dernière migration présente sur le disque ; en cas d'écart, elle affiche une page « Maintenance — lancer `php bin/db.php upgrade` » (HTTP 503).

## 8. Gestion des erreurs

- **Validation** : erreurs affichées par champ, valeurs conservées. Règles : titre 1–200 caractères ; prix ≥ 0 et ≤ 9 999 999,99 (virgule ou point acceptés) ; quantité 1–999 ; URL valide http(s) ; date d'achat valide et non future.
- **Erreurs photo** : message clair (format non supporté, trop lourde, URL inaccessible) ; le reste du formulaire est conservé ; la photo n'est pas obligatoire.
- **404 / 403** : pages dédiées.
- **Erreurs serveur** : handler global → page 500 générique ; détail (message, trace, route, utilisateur) dans `storage/logs/app.log`. Affichage du détail uniquement si `debug = true` dans la config (dev).
- **Appels JS** (commentaires, autocomplétion) : réponses JSON `{ok, error}` ; message d'erreur affiché dans l'interface ; session expirée → redirection vers `/login`.

## 9. Tests

- **PHPUnit** (Composer, dev uniquement), exécuté dans le conteneur de dev contre une base de test dédiée (`wishlist_test`), recréée à chaque exécution :
  - `Validator` : règles des produits, prix, dates.
  - `ItemRepository` : filtres, tri (ordre haute → aucune → basse), totaux, achat / désarchivage, suppression en cascade.
  - `TagRepository` : création à la volée, normalisation, insensibilité à la casse, fusion.
  - `Auth` : connexion, compte désactivé, rate limiting.
  - `UrlGuard` : refus des IP privées / loopback / IPv6 locales / schémas non http.
  - `Migrator` + `SchemaChecker` : migrations → schéma conforme à `schema.php` ; détection de checksum modifié ; détection de colonne manquante.
- **Recette manuelle** : checklist `docs/recette.md` des parcours clés (connexion, ajout avec upload, ajout avec URL d'image, filtres, achat, désarchivage, commentaires mobile, admin création de compte, installation PWA).

## 10. Environnement de développement (Docker, dev uniquement)

- `docker-compose.dev.yml`, projet compose **`wishlist-dev`** (toujours lancé avec `-p wishlist-dev`), isolé des autres conteneurs de la machine :
  - `web` : nginx (config identique à `deploy/nginx.conf.example` adaptée) — port hôte **127.0.0.1:8045**
  - `php` : image PHP 8.3-FPM + extensions requises + Composer, avec `mariadb-client` (pour tester `mysqldump`)
  - `db` : MariaDB — port hôte **127.0.0.1:3345**, volume nommé `wishlist-dev-db`
- Le code est monté en volume ; `config/config.php` de dev est généré depuis l'exemple avec les valeurs de dev.
- Aucune commande Docker globale (prune, suppression de tous les conteneurs…).

## 11. Déploiement production

Documenté pas à pas dans `README.md` :

1. Prérequis : nginx, PHP 8.3-FPM + extensions, MariaDB, `mariadb-client` (mysqldump), git.
2. `git clone` dans `/var/www/wishlist`.
3. Création de la base et d'un utilisateur MariaDB dédié (droits limités à cette base).
4. `cp config/config.example.php config/config.php` puis renseignement (droits 0640, propriétaire `www-data`).
5. `php bin/db.php install` (création des tables + compte admin).
6. Droits d'écriture de `www-data` sur `storage/`.
7. Virtual host nginx à partir de `deploy/nginx.conf.example` (racine `public/`, `try_files` vers `index.php`, blocage des fichiers cachés, `client_max_body_size 10M`) + HTTPS Let's Encrypt (certbot).
8. **Mise à jour** : `git pull` puis `php bin/db.php upgrade`.

## 12. Décisions prises pendant le brainstorming

| Sujet | Décision |
|---|---|
| Partage | Une liste commune, tout le monde modifie tout |
| Photo | Upload + URL d'image (téléchargée et stockée localement) |
| Champs en plus | Titre, quantité, prix réel payé, magasin |
| Fonctions en plus | Filtres, tri, recherche, totaux |
| Commentaires | Fil avec auteur et date |
| Tags | Libres + autocomplétion ; admin renomme/fusionne/supprime |
| Base | MariaDB |
| Usage | PC d'abord pour la saisie ; PWA simple pour consultation et commentaires sur mobile |
| Priorité | haute → aucune → basse |
| Architecture | PHP rendu serveur + JS léger, sans framework |
| Gestion BDD | CLI install/upgrade/check avec migrations versionnées, sauvegarde auto, vérif d'intégrité |
| Comptes | 2 utilisateurs + 1 admin (compte distinct, peut aussi utiliser la liste) |
