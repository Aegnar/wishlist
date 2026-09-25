<?php
declare(strict_types=1);

namespace App\Schema;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(private PDO $db, private string $dir)
    {
    }

    public function ensureTable(): void
    {
        $this->db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(50) NOT NULL,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME NOT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function tableExists(): bool
    {
        return $this->db->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn() !== false;
    }

    /** @return array<string, string> version => chemin du fichier, triés par version */
    public function available(): array
    {
        $files = glob($this->dir . '/[0-9][0-9][0-9]_*.php') ?: [];
        sort($files, SORT_STRING);
        $out = [];
        foreach ($files as $file) {
            $out[basename($file, '.php')] = $file;
        }
        return $out;
    }

    /** @return array<string, string> version => checksum */
    public function applied(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return $this->db
            ->query('SELECT version, checksum FROM schema_migrations ORDER BY version')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** @return list<string> */
    public function pending(): array
    {
        return array_values(array_diff(array_keys($this->available()), array_keys($this->applied())));
    }

    /** @return list<string> messages d'erreur (vide si tout est cohérent) */
    public function verifyChecksums(): array
    {
        $errors = [];
        $available = $this->available();
        foreach ($this->applied() as $version => $checksum) {
            if (!isset($available[$version])) {
                $errors[] = "Migration appliquée absente du disque : $version";
            } elseif (hash_file('sha256', $available[$version]) !== $checksum) {
                $errors[] = "Migration déjà appliquée modifiée depuis : $version (créer une nouvelle migration au lieu de la modifier)";
            }
        }
        return $errors;
    }

    public function currentVersion(): ?string
    {
        $versions = array_keys($this->applied());
        return $versions === [] ? null : (string) end($versions);
    }

    public function isUpToDate(): bool
    {
        return $this->tableExists() && $this->pending() === [];
    }

    /**
     * Applique les migrations en attente, dans l'ordre.
     *
     * @param (callable(string): bool)|null $confirmDestructive appelé pour chaque migration destructive
     * @return list<string> versions appliquées
     */
    public function migrate(?callable $confirmDestructive = null): array
    {
        $errors = $this->verifyChecksums();
        if ($errors !== []) {
            throw new RuntimeException(implode("\n", $errors));
        }
        $this->ensureTable();
        $available = $this->available();
        $done = [];
        foreach ($this->pending() as $version) {
            $migration = require $available[$version];
            if (!$migration instanceof Migration) {
                throw new RuntimeException("$version ne retourne pas une instance de " . Migration::class);
            }
            if ($migration->isDestructive() && !($confirmDestructive !== null && $confirmDestructive($version))) {
                throw new RuntimeException("Migration destructive non confirmée : $version");
            }
            $migration->up($this->db);
            // Enregistrée seulement après succès complet (le DDL MariaDB n'est pas transactionnel).
            $this->db
                ->prepare('INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, NOW())')
                ->execute([$version, hash_file('sha256', $available[$version])]);
            $done[] = $version;
        }
        return $done;
    }
}
