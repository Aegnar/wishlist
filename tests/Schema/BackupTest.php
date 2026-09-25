<?php
declare(strict_types=1);

namespace Tests\Schema;

use App\Schema\Backup;
use RuntimeException;
use Tests\DatabaseTestCase;

final class BackupTest extends DatabaseTestCase
{
    private string $dir;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wl-bk-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testCreatesCompressedDump(): void
    {
        $path = (new Backup(self::dbConfig(), $this->dir))->run();

        self::assertFileExists($path);
        self::assertMatchesRegularExpression('/\d{8}-\d{6}\.sql\.gz$/', $path);
        $sql = gzdecode((string) file_get_contents($path));
        self::assertStringContainsString('CREATE TABLE `items`', $sql);
        self::assertStringContainsString('Dump completed', $sql);
    }

    public function testRotationKeepsNewestFiles(): void
    {
        mkdir($this->dir);
        foreach (['20200101-000000', '20200102-000000', '20200103-000000'] as $stamp) {
            file_put_contents("$this->dir/$stamp.sql.gz", 'old');
        }

        $path = (new Backup(self::dbConfig(), $this->dir, keep: 2))->run();

        $files = array_map('basename', glob($this->dir . '/*.sql.gz'));
        self::assertCount(2, $files);
        self::assertContains('20200103-000000.sql.gz', $files);
        self::assertContains(basename($path), $files);
    }

    public function testMissingBinaryFailsAndLeavesNoFile(): void
    {
        $backup = new Backup(self::dbConfig(), $this->dir, '/nonexistent/mysqldump');

        try {
            $backup->run();
            self::fail('Une sauvegarde en échec doit lever une exception');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('mysqldump', $e->getMessage());
        }
        self::assertSame([], glob($this->dir . '/*.sql.gz'));
    }

    public function testWrongPasswordFails(): void
    {
        $config = ['pass' => 'mauvais'] + self::dbConfig();

        $this->expectException(RuntimeException::class);
        (new Backup($config, $this->dir))->run();
    }
}
