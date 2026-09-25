<?php
declare(strict_types=1);

namespace Tests\Schema;

use App\Schema\SchemaChecker;
use Tests\DatabaseTestCase;

final class SchemaCheckerTest extends DatabaseTestCase
{
    private function checker(): SchemaChecker
    {
        return new SchemaChecker(self::pdo(), require dirname(__DIR__, 2) . '/schema.php');
    }

    protected function setUp(): void
    {
        self::migrateFresh();
    }

    public function testMigrationsProduceExactlyTheExpectedSchema(): void
    {
        $result = $this->checker()->checkStructure();

        self::assertSame([], $result['errors'], implode("\n", $result['errors']));
        self::assertSame([], $result['warnings'], implode("\n", $result['warnings']));
    }

    public function testDetectsMissingColumnAndTable(): void
    {
        self::pdo()->exec('ALTER TABLE items DROP COLUMN store');
        self::pdo()->exec('DROP TABLE login_attempts');

        $errors = $this->checker()->checkStructure()['errors'];

        self::assertContains('Colonne manquante : items.store', $errors);
        self::assertContains('Table manquante : login_attempts', $errors);
    }

    public function testDetectsTypeChangeAndMissingIndex(): void
    {
        self::pdo()->exec('ALTER TABLE items MODIFY title VARCHAR(100) NOT NULL');
        self::pdo()->exec('ALTER TABLE items DROP INDEX idx_items_purchased_date');

        $errors = $this->checker()->checkStructure()['errors'];

        self::assertContains('Type différent pour items.title : attendu varchar(200), trouvé varchar(100)', $errors);
        self::assertContains('Index manquant : items.idx_items_purchased_date', $errors);
    }

    public function testUnknownTableIsOnlyAWarning(): void
    {
        self::pdo()->exec('CREATE TABLE extra_table (id INT)');

        $result = $this->checker()->checkStructure();

        self::assertSame([], $result['errors']);
        self::assertContains('Table inconnue : extra_table', $result['warnings']);
    }

    public function testDataChecks(): void
    {
        $dir = sys_get_temp_dir() . '/wl-chk-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $userId = self::insertUser();
        self::insertItem($userId, ['is_purchased' => 1]);
        self::insertItem($userId, ['image_path' => str_repeat('a', 32) . '.webp']);
        file_put_contents($dir . '/' . str_repeat('b', 32) . '.webp', 'x');

        $result = $this->checker()->checkData($dir);

        self::assertContains('1 produit(s) acheté(s) sans date d\'achat', $result['errors']);
        self::assertContains('Photo manquante sur le disque : ' . str_repeat('a', 32) . '.webp', $result['warnings']);
        self::assertContains('Fichier photo orphelin : ' . str_repeat('b', 32) . '.webp', $result['warnings']);

        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }

    public function testNormalizeType(): void
    {
        self::assertSame('int unsigned', SchemaChecker::normalizeType('int(10) unsigned'));
        self::assertSame('tinyint', SchemaChecker::normalizeType('TINYINT(1)'));
        self::assertSame('varchar(50)', SchemaChecker::normalizeType('varchar(50)'));
        self::assertSame('decimal(10,2)', SchemaChecker::normalizeType('decimal(10,2)'));
    }
}
