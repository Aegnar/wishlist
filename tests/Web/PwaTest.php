<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class PwaTest extends WebTestCase
{
    public function testLayoutDeclaresManifestAndIcons(): void
    {
        $body = $this->request('GET', '/login')->body;

        self::assertStringContainsString('rel="manifest" href="/assets/manifest.webmanifest"', $body);
        self::assertStringContainsString('rel="apple-touch-icon"', $body);
        self::assertStringContainsString('name="theme-color"', $body);
    }

    public function testManifestIsValidAndIconsExist(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $manifest = json_decode((string) file_get_contents($public . '/assets/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('standalone', $manifest['display']);
        self::assertSame('/', $manifest['start_url']);
        foreach ($manifest['icons'] as $icon) {
            $size = getimagesize($public . $icon['src']);
            self::assertSame($icon['sizes'], $size[0] . 'x' . $size[1]);
        }
        self::assertFileExists($public . '/assets/icons/apple-touch-icon.png');
        self::assertFileExists($public . '/assets/icons/favicon-32.png');
    }

    public function testServiceWorkerOnlyCachesOfflineAssets(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $sw = (string) file_get_contents($public . '/sw.js');

        self::assertFileExists($public . '/offline.html');
        self::assertStringContainsString("'/offline.html'", $sw);
        self::assertStringContainsString("event.request.mode !== 'navigate'", $sw);
    }
}
