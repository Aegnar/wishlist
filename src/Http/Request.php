<?php
declare(strict_types=1);

namespace App\Http;

final class Request
{
    private string $method;
    private string $path;

    public function __construct(
        string $method,
        string $path,
        private array $query = [],
        private array $post = [],
        private array $files = [],
        private array $server = [],
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim($path, '/');
    }

    public static function fromGlobals(): self
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            rawurldecode(is_string($path) ? $path : '/'),
            self::scrub($_GET),
            self::scrub($_POST),
            $_FILES,
            $_SERVER,
        );
    }

    /**
     * Remplace les séquences UTF-8 invalides des valeurs (récursivement) : les traitements mb_*,
     * json_encode et la base (utf8mb4 strict) ne rencontrent ainsi que du texte valide.
     *
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public static function scrub(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::scrub($value);
            } elseif (is_string($value)) {
                $values[$key] = mb_scrub($value, 'UTF-8');
            }
        }
        return $values;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function inputAll(): array
    {
        return $this->post;
    }

    /** @return array{name: string, tmp_name: string, error: int, size: int}|null null si aucun fichier envoyé */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        return (int) $file['error'] === UPLOAD_ERR_NO_FILE ? null : $file;
    }

    public function header(string $name): ?string
    {
        $value = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null;
        return is_string($value) ? $value : null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept') ?? '', 'application/json');
    }
}
