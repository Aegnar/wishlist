<?php
declare(strict_types=1);

namespace App\Schema;

use PDO;

final class SchemaChecker
{
    private const IMAGE_PATTERN = '/^([a-f0-9]{32})(_t)?\.webp$/';

    /** @param array<string, array{columns: array<string, array{0: string, 1: bool}>, indexes?: array<string, list<string>>, foreign_keys?: array<string, string>}> $expected */
    public function __construct(private PDO $db, private array $expected)
    {
    }

    /** Supprime la largeur d'affichage des entiers (int(10) → int), met en minuscules. */
    public static function normalizeType(string $columnType): string
    {
        return (string) preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower(trim($columnType)));
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    public function checkStructure(): array
    {
        $errors = [];
        $warnings = [];
        $columns = $this->loadColumns();
        $indexes = $this->loadIndexes();
        $foreignKeys = $this->loadForeignKeys();

        foreach ($this->expected as $table => $definition) {
            if (!isset($columns[$table])) {
                $errors[] = "Table manquante : $table";
                continue;
            }
            foreach ($definition['columns'] as $column => [$type, $nullable]) {
                if (!isset($columns[$table][$column])) {
                    $errors[] = "Colonne manquante : $table.$column";
                    continue;
                }
                [$actualType, $actualNullable] = $columns[$table][$column];
                if ($actualType !== $type) {
                    $errors[] = "Type différent pour $table.$column : attendu $type, trouvé $actualType";
                }
                if ($actualNullable !== $nullable) {
                    $errors[] = "Nullabilité différente pour $table.$column : attendu " . ($nullable ? 'NULL' : 'NOT NULL');
                }
            }
            foreach (array_keys(array_diff_key($columns[$table], $definition['columns'])) as $column) {
                $warnings[] = "Colonne inconnue : $table.$column";
            }
            foreach ($definition['indexes'] ?? [] as $name => $indexColumns) {
                if (!isset($indexes[$table][$name])) {
                    $errors[] = "Index manquant : $table.$name";
                } elseif ($indexes[$table][$name] !== $indexColumns) {
                    $errors[] = "Index $table.$name : colonnes attendues (" . implode(', ', $indexColumns) . ')';
                }
            }
            foreach ($definition['foreign_keys'] ?? [] as $column => $reference) {
                if (($foreignKeys[$table][$column] ?? null) !== $reference) {
                    $errors[] = "Clé étrangère manquante : $table.$column → $reference";
                }
            }
        }
        foreach (array_keys(array_diff_key($columns, $this->expected)) as $table) {
            $warnings[] = "Table inconnue : $table";
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    public function checkData(string $uploadsDir): array
    {
        $errors = [];
        $warnings = [];

        $count = (int) $this->db
            ->query('SELECT COUNT(*) FROM items WHERE is_purchased = 1 AND purchased_at IS NULL')
            ->fetchColumn();
        if ($count > 0) {
            $errors[] = "$count produit(s) acheté(s) sans date d'achat";
        }

        $referenced = [];
        foreach ($this->db->query('SELECT image_path FROM items WHERE image_path IS NOT NULL') as $row) {
            $name = (string) $row['image_path'];
            if (preg_match(self::IMAGE_PATTERN, $name, $m)) {
                $referenced[$m[1]] = true;
            }
            if (!is_file($uploadsDir . '/' . $name)) {
                $warnings[] = "Photo manquante sur le disque : $name";
            }
        }

        foreach (glob($uploadsDir . '/*.webp') ?: [] as $file) {
            $name = basename($file);
            if (preg_match(self::IMAGE_PATTERN, $name, $m) && !isset($referenced[$m[1]])) {
                $warnings[] = "Fichier photo orphelin : $name";
            }
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /** @return array<string, array<string, array{0: string, 1: bool}>> */
    private function loadColumns(): array
    {
        $rows = $this->db->query(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS type, IS_NULLABLE AS nullable
             FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['t']][$row['c']] = [self::normalizeType($row['type']), $row['nullable'] === 'YES'];
        }
        return $out;
    }

    /** @return array<string, array<string, list<string>>> */
    private function loadIndexes(): array
    {
        $rows = $this->db->query(
            'SELECT TABLE_NAME AS t, INDEX_NAME AS i, COLUMN_NAME AS c
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['t']][$row['i']][] = $row['c'];
        }
        return $out;
    }

    /** @return array<string, array<string, string>> */
    private function loadForeignKeys(): array
    {
        $rows = $this->db->query(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c, REFERENCED_TABLE_NAME AS rt, REFERENCED_COLUMN_NAME AS rc
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['t']][$row['c']] = $row['rt'] . '.' . $row['rc'];
        }
        return $out;
    }
}
