<?php
declare(strict_types=1);

namespace App\Schema;

use RuntimeException;

final class Backup
{
    /** @param array{host: string, port?: int, name: string, user: string, pass: string} $db */
    public function __construct(
        private array $db,
        private string $dir,
        private string $mysqldump = 'mysqldump',
        private int $keep = 10,
    ) {
    }

    /** @return string chemin du fichier .sql.gz créé */
    public function run(): string
    {
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new RuntimeException("Impossible de créer le dossier de sauvegarde : $this->dir");
        }
        $target = sprintf('%s/%s.sql.gz', $this->dir, date('Ymd-His'));
        $optionsFile = $this->writeOptionsFile();
        try {
            // --defaults-extra-file doit être la première option. Le mot de passe n'apparaît jamais en ligne de commande.
            $command = sprintf(
                '%s --defaults-extra-file=%s --single-transaction --routines --no-tablespaces %s',
                escapeshellarg($this->mysqldump),
                escapeshellarg($optionsFile),
                escapeshellarg($this->db['name']),
            );
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Impossible de lancer mysqldump');
            }
            $gz = gzopen($target, 'wb6');
            $tail = '';
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                gzwrite($gz, $chunk);
                $tail = substr($tail . $chunk, -512);
            }
            gzclose($gz);
            $stderr = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);

            if ($code !== 0 || !str_contains($tail, 'Dump completed')) {
                @unlink($target);
                throw new RuntimeException("Échec de mysqldump (code $code) : " . ($stderr !== '' ? $stderr : 'dump incomplet'));
            }
        } finally {
            @unlink($optionsFile);
        }
        $this->rotate();
        return $target;
    }

    /** Conserve uniquement les $keep sauvegardes les plus récentes. */
    public function rotate(): void
    {
        $files = glob($this->dir . '/*.sql.gz') ?: [];
        sort($files, SORT_STRING);
        foreach (array_slice($files, 0, max(0, count($files) - $this->keep)) as $old) {
            @unlink($old);
        }
    }

    private function writeOptionsFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'wlbk');
        if ($file === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire de sauvegarde');
        }
        chmod($file, 0600);
        file_put_contents($file, sprintf(
            "[client]\nhost=%s\nport=%d\nuser=\"%s\"\npassword=\"%s\"\n",
            $this->db['host'],
            (int) ($this->db['port'] ?? 3306),
            addcslashes($this->db['user'], "\\\""),
            addcslashes($this->db['pass'], "\\\""),
        ));
        return $file;
    }
}
