<?php
declare(strict_types=1);

namespace Tests;

use App\ImageException;
use App\ImageStore;
use App\UrlGuard;
use PHPUnit\Framework\TestCase;

final class ImageStoreTest extends TestCase
{
    private string $dir;
    private ImageStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wl-img-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->store = new ImageStore($this->dir, new UrlGuard(static fn (string $h): array => ['10.0.0.1']));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function png(int $width, int $height): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 100, 50));
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    public function testStoresResizedImageAndThumbnail(): void
    {
        $name = $this->store->storeBytes($this->png(2000, 1000));

        self::assertMatchesRegularExpression(ImageStore::NAME_PATTERN, $name);
        $main = getimagesize($this->dir . '/' . $name);
        $thumb = getimagesize($this->dir . '/' . str_replace('.webp', '_t.webp', $name));
        self::assertSame([1600, 800, 'image/webp'], [$main[0], $main[1], $main['mime']]);
        self::assertSame([400, 200], [$thumb[0], $thumb[1]]);
    }

    public function testSmallImageIsNotUpscaled(): void
    {
        $name = $this->store->storeBytes($this->png(300, 200));

        self::assertSame([300, 200], array_slice(getimagesize($this->dir . '/' . $name), 0, 2));
    }

    public function testRejectsNonImages(): void
    {
        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('Format non supporté');
        $this->store->storeBytes('<?php echo "pwned";');
    }

    public function testStoreUploadReadsTempFileAndChecksErrors(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $this->png(50, 50));

        $name = $this->store->storeUpload(['name' => 'a.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]);
        self::assertFileExists($this->dir . '/' . $name);
        unlink($tmp);

        $this->expectException(ImageException::class);
        $this->store->storeUpload(['name' => 'a.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]);
    }

    public function testStoreFromUrlRefusesPrivateTargets(): void
    {
        $this->expectException(ImageException::class);
        $this->expectExceptionMessage('non autorisée');
        $this->store->storeFromUrl('https://interne.test/a.png');
    }

    public function testDeleteAndPath(): void
    {
        $name = $this->store->storeBytes($this->png(10, 10));

        self::assertSame($this->dir . '/' . $name, $this->store->path($name));
        self::assertNull($this->store->path('../config.php'));
        self::assertNull($this->store->path(str_repeat('f', 32) . '.webp'));

        $this->store->delete($name);
        self::assertSame([], glob($this->dir . '/*'));
        $this->store->delete(null);
        $this->store->delete('../../etc/passwd');
    }
}
