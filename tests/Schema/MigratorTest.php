<?php
declare(strict_types=1);

namespace Tests\Schema;

use App\Schema\Migrator;
use RuntimeException;
use Tests\DatabaseTestCase;

final class MigratorTest extends DatabaseTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        self::dropAllTables();
        $this->tmpDir = sys_get_temp_dir() . '/wl-migr-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
        self::dropAllTables();
    }

    private function writeMigration(string $name, string $body, bool $destructive = false): void
    {
        $flag = $destructive ? 'true' : 'false';
        file_put_contents("$this->tmpDir/$name.php", <<<PHP
            <?php
            declare(strict_types=1);
            return new class implements App\Schema\Migration {
                public function isDestructive(): bool { return $flag; }
                public function up(PDO \$db): void { $body }
            };
            PHP);
    }

    public function testRealMigrationsCreateAllTables(): void
    {
        $migrator = new Migrator(self::pdo(), dirname(__DIR__, 2) . '/migrations');
        self::assertFalse($migrator->isUpToDate());

        $applied = $migrator->migrate();

        self::assertContains('001_init', $applied);
        self::assertTrue($migrator->isUpToDate());
        $tables = self::pdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        foreach (['users', 'items', 'tags', 'item_tag', 'comments', 'login_attempts', 'schema_migrations'] as $table) {
            self::assertContains($table, $tables);
        }
        self::assertSame([], $migrator->migrate(), 'une deuxième exécution ne fait rien');
    }

    public function testAppliesInOrderAndRecordsVersion(): void
    {
        $this->writeMigration('002_b', '$db->exec("CREATE TABLE tmp_b (id INT)");');
        $this->writeMigration('001_a', '$db->exec("CREATE TABLE tmp_a (id INT)");');
        $migrator = new Migrator(self::pdo(), $this->tmpDir);

        self::assertSame(['001_a', '002_b'], $migrator->pending());
        self::assertSame(['001_a', '002_b'], $migrator->migrate());
        self::assertSame('002_b', $migrator->currentVersion());
        self::assertSame([], $migrator->pending());
    }

    public function testDetectsModifiedMigration(): void
    {
        $this->writeMigration('001_a', '$db->exec("CREATE TABLE tmp_a (id INT)");');
        $migrator = new Migrator(self::pdo(), $this->tmpDir);
        $migrator->migrate();

        $this->writeMigration('001_a', '$db->exec("CREATE TABLE tmp_a (id BIGINT)");');

        self::assertCount(1, $migrator->verifyChecksums());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('001_a');
        $migrator->migrate();
    }

    public function testDetectsAppliedMigrationMissingFromDisk(): void
    {
        $this->writeMigration('001_a', '$db->exec("CREATE TABLE tmp_a (id INT)");');
        $migrator = new Migrator(self::pdo(), $this->tmpDir);
        $migrator->migrate();
        unlink("$this->tmpDir/001_a.php");

        self::assertStringContainsString('absente', $migrator->verifyChecksums()[0]);
    }

    public function testDestructiveMigrationNeedsConfirmation(): void
    {
        $this->writeMigration('001_a', '$db->exec("CREATE TABLE tmp_a (id INT)");', destructive: true);
        $migrator = new Migrator(self::pdo(), $this->tmpDir);

        try {
            $migrator->migrate(static fn (string $version): bool => false);
            self::fail('Une migration destructive non confirmée doit être refusée');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('destructive', $e->getMessage());
        }
        self::assertSame(['001_a'], $migrator->pending());

        self::assertSame(['001_a'], $migrator->migrate(static fn (string $version): bool => true));
    }
}
