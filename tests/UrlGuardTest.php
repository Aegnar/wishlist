<?php
declare(strict_types=1);

namespace Tests;

use App\UrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlGuardTest extends TestCase
{
    public static function blockedIps(): array
    {
        return array_map(static fn (string $ip): array => [$ip], [
            '127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.10', '169.254.169.254',
            '0.0.0.0', '100.64.0.1', '::1', 'fe80::1', 'fc00::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1',
        ]);
    }

    #[DataProvider('blockedIps')]
    public function testPrivateAndLocalIpsAreBlocked(string $ip): void
    {
        self::assertFalse(UrlGuard::isPublicIp($ip));
    }

    public function testPublicIpsAreAllowed(): void
    {
        self::assertTrue(UrlGuard::isPublicIp('93.184.216.34'));
        self::assertTrue(UrlGuard::isPublicIp('2606:4700:4700::1111'));
    }

    public function testCheckResolvesAndReturnsTarget(): void
    {
        $guard = new UrlGuard(static fn (string $host): array => ['93.184.216.34']);

        $target = $guard->check('https://images.exemple.test/a.jpg');

        self::assertSame('images.exemple.test', $target['host']);
        self::assertSame(443, $target['port']);
        self::assertSame('93.184.216.34', $target['ip']);
    }

    public static function rejectedUrls(): array
    {
        return [
            ['ftp://exemple.test/a.png'],
            ['file:///etc/passwd'],
            ['https://user:pass@exemple.test/a.png'],
            ['https://exemple.test:8443/a.png'],
            ['http://127.0.0.1/a.png'],
            ['http://[::1]/a.png'],
            ['https://interne.test/a.png'],
            ['https://mixte.test/a.png'],
            ['https://inconnu.test/a.png'],
            ['pas une url'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function testCheckRejects(string $url): void
    {
        $guard = new UrlGuard(static fn (string $host): array => match ($host) {
            'interne.test' => ['10.0.0.5'],
            'mixte.test' => ['93.184.216.34', '192.168.0.1'],
            'inconnu.test' => [],
            default => ['93.184.216.34'],
        });

        $this->expectException(InvalidArgumentException::class);
        $guard->check($url);
    }
}
