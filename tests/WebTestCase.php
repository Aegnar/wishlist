<?php
declare(strict_types=1);

namespace Tests;

use App\App;
use App\Csrf;
use App\Http\Request;
use App\Http\Response;
use App\Kernel;
use App\Router;

abstract class WebTestCase extends DatabaseTestCase
{
    protected App $app;
    protected string $storage;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $_SESSION = [];
        $this->storage = sys_get_temp_dir() . '/wl-web-' . bin2hex(random_bytes(4));
        mkdir($this->storage . '/uploads', 0777, true);
        $this->app = new App([
            'db' => self::dbConfig(),
            'app' => ['debug' => true, 'timezone' => 'Europe/Paris', 'session_name' => 'wl_test', 'cookie_secure' => false],
            'paths' => ['storage' => $this->storage],
            'backup' => ['mysqldump' => 'mysqldump', 'keep' => 10],
        ], dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        self::removeDir($this->storage);
        $_SESSION = [];
    }

    private static function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $path) {
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @param array<string, string> $headers clés au format $_SERVER, ex. HTTP_ACCEPT */
    protected function request(string $method, string $uri, array $data = [], array $files = [], array $headers = []): Response
    {
        $parts = parse_url($uri);
        parse_str($parts['query'] ?? '', $query);
        if (strtoupper($method) === 'POST' && !array_key_exists('_csrf', $data)) {
            $data['_csrf'] = Csrf::token();
        }
        $request = new Request($method, $parts['path'] ?? '/', $query, $data, $files, ['REMOTE_ADDR' => '127.0.0.1'] + $headers);
        $router = new Router();
        (require dirname(__DIR__) . '/src/routes.php')($router, $this->app);
        return (new Kernel($this->app, $router))->handle($request);
    }

    /** Simule une nouvelle requête HTTP : nouvelle App, donc un Auth sans utilisateur en cache. */
    protected function newRequestCycle(): void
    {
        $this->app = new App($this->app->config, dirname(__DIR__));
    }

    protected function createUser(string $username = 'alice', string $role = 'user', string $password = 'motdepasse-solide'): array
    {
        $id = $this->app->users->create($username, ucfirst($username), $password, $role);
        return $this->app->users->find($id);
    }

    protected function loginAs(array $user): void
    {
        $this->app->auth->login($user, false);
    }

    /** @param array<string, mixed> $overrides champs de Validator::item()['data'] */
    protected function createItem(int $userId, array $overrides = []): int
    {
        return $this->app->items->create($overrides + [
            'title' => 'Objet',
            'description' => null,
            'url' => null,
            'store' => null,
            'price_estimated' => null,
            'quantity' => 1,
            'priority' => 'none',
            'tags' => [],
            'image_url' => null,
        ], $userId);
    }
}
