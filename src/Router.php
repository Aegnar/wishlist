<?php
declare(strict_types=1);

namespace App;

final class Router
{
    /** @var list<array{0: string, 1: string, 2: callable}> méthode, regex, handler */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $this->compile($pattern), $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $this->compile($pattern), $handler];
    }

    /** @return array{0: callable, 1: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod === $method && preg_match($regex, $path, $matches)) {
                return [$handler, array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        return null;
    }

    private function compile(string $pattern): string
    {
        // {id} = entier ; autres paramètres : pas de "/" et ne commencent jamais par "." (bloque "..").
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => $m[1] === 'id'
                ? '(?P<id>\d+)'
                : '(?P<' . $m[1] . '>[A-Za-z0-9_-][A-Za-z0-9_.-]*)',
            $pattern,
        );
        return '#^' . $regex . '$#';
    }
}
