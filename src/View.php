<?php
declare(strict_types=1);

namespace App;

use RuntimeException;
use Throwable;

final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private string $dir)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** Rend un template ; s'il y a un layout, le contenu lui est passé dans $content. */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $data += $this->shared;
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, ['content' => $content] + $data);
    }

    /** Rend un fragment (les données partagées restent disponibles). */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data + $this->shared);
    }

    private function renderFile(string $template, array $data): string
    {
        $__file = $this->dir . '/' . $template . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException("Template introuvable : $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
