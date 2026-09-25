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
        private int $timeout = 600,
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
            if ($gz === false) {
                proc_terminate($process);
                proc_close($process);
                throw new RuntimeException("Impossible d'ouvrir le fichier de sauvegarde en écriture : $target");
            }

            ['tail' => $tail, 'stderr' => $stderr, 'failure' => $failure] = $this->pump($pipes, $gz);
            fclose($pipes[1]);
            fclose($pipes[2]);

            if ($failure === 'timeout') {
                proc_terminate($process);
                proc_close($process);
                @unlink($target);
                throw new RuntimeException("Sauvegarde interrompue : délai maximal dépassé ({$this->timeout}s)");
            }
            if ($failure === 'write') {
                proc_terminate($process);
                proc_close($process);
                @unlink($target);
                throw new RuntimeException("Échec d'écriture de la sauvegarde compressée (disque plein ?)");
            }

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

    /**
     * Lit stdout et stderr sans blocage (stream_select), en écrivant stdout dans $gz au fur
     * et à mesure : lire un flux jusqu'à la fin avant l'autre bloquerait indéfiniment si le
     * flux ignoré se remplit (deadlock). Le tampon d'écriture est vérifié à chaque appel pour
     * détecter un disque plein ou une écriture partielle, et une limite de temps totale évite
     * qu'un mysqldump bloqué (ex. verrou) ne pende indéfiniment.
     *
     * @param array<int, resource> $pipes
     * @param resource $gz
     * @return array{tail: string, stderr: string, failure: 'timeout'|'write'|null}
     */
    private function pump(array $pipes, $gz): array
    {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $tail = '';
        $stderr = '';
        /** @var array<int, resource> $open */
        $open = [1 => $pipes[1], 2 => $pipes[2]];
        $deadline = microtime(true) + $this->timeout;

        while ($open !== []) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                gzclose($gz);
                return ['tail' => $tail, 'stderr' => $stderr, 'failure' => 'timeout'];
            }
            $read = array_values($open);
            $write = null;
            $except = null;
            $ready = @stream_select($read, $write, $except, (int) $remaining, (int) (($remaining - floor($remaining)) * 1_000_000));
            if ($ready === false || $ready === 0) {
                continue; // signal interrompu ou rien à lire pour l'instant : le délai est revérifié en haut de boucle
            }
            foreach ($read as $stream) {
                $fd = $stream === $pipes[1] ? 1 : 2;
                $chunk = fread($stream, 65536);
                if ($chunk === false || $chunk === '') {
                    if (feof($stream)) {
                        unset($open[$fd]);
                    }
                    continue;
                }
                if ($fd === 1) {
                    $written = gzwrite($gz, $chunk);
                    if ($written === false || $written < strlen($chunk)) {
                        gzclose($gz);
                        return ['tail' => $tail, 'stderr' => $stderr, 'failure' => 'write'];
                    }
                    $tail = substr($tail . $chunk, -512);
                } else {
                    $stderr .= $chunk;
                }
            }
        }
        gzclose($gz);
        return ['tail' => $tail, 'stderr' => trim($stderr), 'failure' => null];
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
