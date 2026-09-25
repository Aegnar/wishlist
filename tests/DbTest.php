<?php
declare(strict_types=1);

namespace Tests;

use App\Config;

final class DbTest extends DatabaseTestCase
{
    public function testConnectsWithUtf8mb4AndNativePrepares(): void
    {
        $pdo = self::pdo();
        self::assertSame(1, $pdo->query('SELECT 1')->fetchColumn());
        self::assertSame('utf8mb4', $pdo->query('SELECT @@character_set_connection')->fetchColumn());
        // pdo_mysql/mysqlnd renvoie int(0) et non bool(false) pour cet attribut : cast explicite.
        self::assertFalse((bool) $pdo->getAttribute(\PDO::ATTR_EMULATE_PREPARES));
    }

    public function testConfigNormalizeFillsDefaults(): void
    {
        $config = Config::normalize(['db' => ['host' => 'x'], 'app' => []]);
        self::assertFalse($config['app']['debug']);
        self::assertSame('Europe/Paris', $config['app']['timezone']);
        self::assertTrue($config['app']['cookie_secure']);
        self::assertSame(10, $config['backup']['keep']);
        self::assertStringEndsWith('storage', $config['paths']['storage']);
    }

    public function testConfigLoadRejectsMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        Config::load('/nonexistent/config.php');
    }

    public function testEscapeHelper(): void
    {
        self::assertSame('&lt;b&gt; &quot;x&quot; &#039;y&#039; &amp;', e('<b> "x" \'y\' &'));
        self::assertSame('', e(null));
    }
}
