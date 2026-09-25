<?php
declare(strict_types=1);

namespace App;

use Throwable;

final class Logger
{
    public function __construct(private string $file)
    {
    }

    public function error(Throwable $e, array $context = []): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s: %s in %s:%d %s\n%s\n",
            date('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $e->getTraceAsString(),
        );
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
