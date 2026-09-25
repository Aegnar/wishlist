<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\TagRepository;
use InvalidArgumentException;
use Tests\DatabaseTestCase;

final class TagRepositoryTest extends DatabaseTestCase
{
    private TagRepository $tags;
    private int $userId;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $this->tags = new TagRepository(self::pdo());
        $this->userId = self::insertUser();
    }

    public function testFindOrCreateIsCaseInsensitiveAndNormalized(): void
    {
        $id = $this->tags->findOrCreate('  Salle   de bain ');

        self::assertSame($id, $this->tags->findOrCreate('salle de BAIN'));
        self::assertSame('Salle de bain', $this->tags->find($id)['name']);
    }

    public function testFindOrCreateRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->tags->findOrCreate('  ,  ');
    }

    public function testSyncItemTagsReplacesTags(): void
    {
        $item = self::insertItem($this->userId);
        $other = self::insertItem($this->userId);

        $this->tags->syncItemTags($item, ['meuble', 'Salon']);
        $this->tags->syncItemTags($other, ['voyage']);
        $this->tags->syncItemTags($item, ['Salon', 'déco']);

        self::assertSame([$item => ['déco', 'Salon'], $other => ['voyage']], $this->tags->namesForItems([$item, $other]));
        self::assertSame([], $this->tags->namesForItems([]));
    }

    public function testSuggestPutsPrefixMatchesFirstAndEscapesWildcards(): void
    {
        foreach (['Bijoux', 'Jardin', 'Déco', '100% coton'] as $name) {
            $this->tags->findOrCreate($name);
        }

        self::assertSame(['Jardin', 'Bijoux'], $this->tags->suggest('j'));
        self::assertSame(['100% coton'], $this->tags->suggest('%'));
        self::assertCount(1, $this->tags->suggest('j', 1));
    }

    public function testAllNamesAndCounts(): void
    {
        $item = self::insertItem($this->userId);
        $this->tags->syncItemTags($item, ['meuble']);
        $this->tags->findOrCreate('voyage');

        self::assertSame(['meuble', 'voyage'], $this->tags->allNames());
        self::assertSame(
            [['meuble', 1], ['voyage', 0]],
            array_map(static fn (array $t): array => [$t['name'], $t['usage_count']], $this->tags->allWithCounts()),
        );
    }

    public function testRename(): void
    {
        $id = $this->tags->findOrCreate('meubel');
        $this->tags->findOrCreate('déco');

        $this->tags->rename($id, 'Meuble');
        self::assertSame('Meuble', $this->tags->find($id)['name']);

        $this->tags->rename($id, 'meuble');
        self::assertSame('meuble', $this->tags->find($id)['name'], 'changement de casse autorisé');

        $this->expectException(InvalidArgumentException::class);
        $this->tags->rename($id, 'DÉCO');
    }

    public function testMergeMovesLinksWithoutDuplicates(): void
    {
        $a = self::insertItem($this->userId);
        $b = self::insertItem($this->userId);
        $this->tags->syncItemTags($a, ['meubles', 'meuble']);
        $this->tags->syncItemTags($b, ['meubles']);
        $from = $this->tags->findByName('meubles')['id'];
        $to = $this->tags->findByName('meuble')['id'];

        $this->tags->merge($from, $to);

        self::assertNull($this->tags->find($from));
        self::assertSame([$a => ['meuble'], $b => ['meuble']], $this->tags->namesForItems([$a, $b]));
    }

    public function testMergeIntoItselfFails(): void
    {
        $id = $this->tags->findOrCreate('x');

        $this->expectException(InvalidArgumentException::class);
        $this->tags->merge($id, $id);
    }

    public function testDeleteIfUnused(): void
    {
        $item = self::insertItem($this->userId);
        $this->tags->syncItemTags($item, ['utilisé']);
        $unused = $this->tags->findOrCreate('libre');
        $used = $this->tags->findByName('utilisé')['id'];

        self::assertTrue($this->tags->deleteIfUnused($unused));
        self::assertFalse($this->tags->deleteIfUnused($used));
        self::assertNotNull($this->tags->find($used));
    }
}
