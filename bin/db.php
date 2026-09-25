#!/usr/bin/env php
<?php
declare(strict_types=1);

// Gestion de la base : php bin/db.php install|upgrade|check|status|backup|help
// À lancer en SSH (jamais via le web), idéalement en tant que www-data :
//   sudo -u www-data php bin/db.php upgrade

use App\Config;
use App\Db;
use App\Repository\UserRepository;
use App\Schema\Backup;
use App\Schema\Migrator;
use App\Schema\SchemaChecker;
use App\Validator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    $interactive = $hidden && stream_isatty(STDIN);
    if ($interactive) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($interactive) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $line === false ? '' : trim($line);
}

/** Demande une valeur jusqu'à ce que $validate retourne null ; abandonne si l'entrée est fermée. */
function ask(string $label, callable $validate, bool $hidden = false): string
{
    while (true) {
        if (feof(STDIN)) {
            throw new RuntimeException('Entrée interrompue');
        }
        $value = prompt($label, $hidden);
        $error = $validate($value);
        if ($error === null) {
            return $value;
        }
        err($error);
    }
}

function runCheck(Migrator $migrator, SchemaChecker $checker, string $uploadsDir): int
{
    $errors = $migrator->verifyChecksums();
    $warnings = array_map(static fn (string $v): string => "Migration en attente : $v", $migrator->pending());
    $structure = $checker->checkStructure();
    $errors = [...$errors, ...$structure['errors']];
    $warnings = [...$warnings, ...$structure['warnings']];
    if ($structure['errors'] === []) {
        $data = $checker->checkData($uploadsDir);
        $errors = [...$errors, ...$data['errors']];
        $warnings = [...$warnings, ...$data['warnings']];
    }
    foreach ($errors as $e) {
        err("  ✗ $e");
    }
    foreach ($warnings as $w) {
        out("  ! $w");
    }
    if ($errors === []) {
        out('Intégrité : OK' . ($warnings === [] ? '' : ' (avec ' . count($warnings) . ' avertissement(s))'));
        return 0;
    }
    err('Intégrité : ' . count($errors) . ' erreur(s)');
    return 1;
}

$command = $argv[1] ?? 'help';
$options = array_slice($argv, 2);
$root = dirname(__DIR__);

if ($command === 'help' || !in_array($command, ['install', 'upgrade', 'check', 'status', 'backup'], true)) {
    out(<<<TXT
        Usage : php bin/db.php <commande>

          install    Crée les tables et le compte administrateur (base vide uniquement)
          upgrade    Sauvegarde puis applique les migrations en attente, puis vérifie
                     (--allow-destructive : accepte les migrations destructives sans question)
          check      Vérifie l'intégrité du schéma et des données (ne modifie rien)
          status     Affiche la version et les migrations en attente
          backup     Sauvegarde manuelle de la base dans storage/backups/
        TXT);
    exit($command === 'help' ? 0 : 1);
}

try {
    $config = Config::load();
    date_default_timezone_set($config['app']['timezone']);
    $pdo = Db::connect($config['db']);
} catch (Throwable $e) {
    err('Erreur : ' . $e->getMessage());
    exit(1);
}

$migrator = new Migrator($pdo, $root . '/migrations');
$checker = new SchemaChecker($pdo, require $root . '/schema.php');
$storage = $config['paths']['storage'];
$backup = new Backup($config['db'], $storage . '/backups', (string) $config['backup']['mysqldump'], (int) $config['backup']['keep']);

try {
    switch ($command) {
        case 'install':
            if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) {
                err('La base contient déjà des tables : utiliser "php bin/db.php upgrade".');
                exit(1);
            }
            foreach ($migrator->migrate() as $version) {
                out("  ✓ $version");
            }
            out('Création du compte administrateur');
            $username = '';
            ask('Identifiant : ', static function (string $v) use (&$username): ?string {
                $username = strtolower($v);
                return Validator::username($username);
            });
            $displayName = ask('Nom affiché : ', static fn (string $v): ?string => Validator::displayName($v));
            $password = ask(
                'Mot de passe (10 caractères minimum) : ',
                static fn (string $v): ?string => Validator::password($v, prompt('Confirmer le mot de passe : ', true)),
                true,
            );
            (new UserRepository($pdo))->create($username, trim($displayName), $password, 'admin');
            out("Compte administrateur « $username » créé.");
            exit(runCheck($migrator, $checker, $storage . '/uploads'));

        case 'upgrade':
            $errors = $migrator->verifyChecksums();
            if ($errors !== []) {
                array_map('err', $errors);
                exit(1);
            }
            if ($migrator->pending() === []) {
                out('Base déjà à jour (' . ($migrator->currentVersion() ?? 'vide') . ').');
                exit(runCheck($migrator, $checker, $storage . '/uploads'));
            }
            out('Sauvegarde avant mise à jour…');
            out('  → ' . $backup->run());
            $allow = in_array('--allow-destructive', $options, true);
            $applied = $migrator->migrate(static function (string $version) use ($allow): bool {
                return $allow || prompt("La migration $version est DESTRUCTIVE. Taper OUI pour continuer : ") === 'OUI';
            });
            foreach ($applied as $version) {
                out("  ✓ $version");
            }
            exit(runCheck($migrator, $checker, $storage . '/uploads'));

        case 'check':
            exit(runCheck($migrator, $checker, $storage . '/uploads'));

        case 'status':
            out('Version actuelle : ' . ($migrator->currentVersion() ?? 'aucune (base non installée)'));
            foreach (array_keys($migrator->applied()) as $version) {
                out("  ✓ $version");
            }
            foreach ($migrator->pending() as $version) {
                out("  … $version (en attente)");
            }
            exit(0);

        case 'backup':
            out('Sauvegarde : ' . $backup->run());
            exit(0);
    }
} catch (Throwable $e) {
    err('Erreur : ' . $e->getMessage());
    exit(1);
}
