<?php
declare(strict_types=1);

namespace Tests\Repository;

use App\ItemFilter;
use App\Repository\ItemRepository;
use App\Repository\TagRepository;
use Tests\DatabaseTestCase;

final class ItemRepositoryTest extends DatabaseTestCase
{
    private ItemRepository $items;
    private int $alice;
    private int $bob;

    public static function setUpBeforeClass(): void
    {
        self::migrateFresh();
    }

    protected function setUp(): void
    {
        self::truncateData();
        $this->items = new ItemRepository(self::pdo(), new TagRepository(self::pdo()));
        $this->alice = self::insertUser('alice');
        $this->bob = self::insertUser('bob');
    }

    /** @param array<string, mixed> $overrides */
    private function data(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Lampe',
            'description' => null,
            'url' => null,
            'store' => null,
            'price_estimated' => null,
            'quantity' => 1,
            'priority' => 'none',
            'tags' => [],
            'image_url' => null,
        ];
    }

    private function titles(ItemFilter $filter): array
    {
        return array_column($this->items->search($filter), 'title');
    }

    public function testCreateAndFind(): void
    {
        $id = $this->items->create($this->data([
            'title' => 'Canapé',
            'store' => 'IKEA',
            'price_estimated' => '499.00',
            'quantity' => 2,
            'priority' => 'high',
            'tags' => ['meuble', 'salon'],
        ]), $this->alice);

        $item = $this->items->find($id);

        self::assertSame('Canapé', $item['title']);
        self::assertSame('IKEA', $item['store']);
        self::assertSame('499.00', $item['price_estimated']);
        self::assertSame(2, $item['quantity']);
        self::assertSame('high', $item['priority']);
        self::assertSame(0, $item['is_purchased']);
        self::assertSame('Alice', $item['created_by_name']);
        self::assertNull($item['purchased_by_name']);
        self::assertSame(['meuble', 'salon'], $item['tags']);
        self::assertNull($this->items->find(9999));
    }

    public function testUpdateReplacesFieldsAndTags(): void
    {
        $id = $this->items->create($this->data(['tags' => ['a', 'b']]), $this->alice);

        $this->items->update($id, $this->data(['title' => 'Lampe de bureau', 'tags' => ['b', 'c']]));

        $item = $this->items->find($id);
        self::assertSame('Lampe de bureau', $item['title']);
        self::assertSame(['b', 'c'], $item['tags']);
    }

    public function testPriorityOrderIsHighNoneLow(): void
    {
        self::insertItem($this->alice, ['title' => 'Basse', 'priority' => 'low']);
        self::insertItem($this->alice, ['title' => 'Aucune', 'priority' => 'none']);
        self::insertItem($this->alice, ['title' => 'Haute', 'priority' => 'high']);

        self::assertSame(['Haute', 'Aucune', 'Basse'], $this->titles(ItemFilter::fromQuery([], false)));
    }

    public function testPriceSortKeepsUnpricedLast(): void
    {
        self::insertItem($this->alice, ['title' => 'Sans prix']);
        self::insertItem($this->alice, ['title' => 'Cher', 'price_estimated' => '100.00']);
        self::insertItem($this->alice, ['title' => 'Pas cher', 'price_estimated' => '5.00']);

        self::assertSame(['Pas cher', 'Cher', 'Sans prix'], $this->titles(ItemFilter::fromQuery(['sort' => 'price_asc'], false)));
        self::assertSame(['Cher', 'Pas cher', 'Sans prix'], $this->titles(ItemFilter::fromQuery(['sort' => 'price_desc'], false)));
    }

    public function testFilters(): void
    {
        $lamp = $this->items->create($this->data(['title' => 'Lampe', 'store' => 'Leroy', 'priority' => 'high', 'tags' => ['déco']]), $this->alice);
        $this->items->create($this->data(['title' => 'Valise', 'description' => 'pour le voyage', 'tags' => ['voyage']]), $this->alice);
        $this->items->create($this->data(['title' => 'Stylo']), $this->alice);
        $this->items->markPurchased(
            $this->items->create($this->data(['title' => 'Lampadaire', 'tags' => ['déco']]), $this->alice),
            '2026-09-01',
            null,
            $this->bob,
        );

        self::assertSame(['Lampe'], $this->titles(ItemFilter::fromQuery(['q' => 'leroy'], false)));
        self::assertSame(['Valise'], $this->titles(ItemFilter::fromQuery(['q' => 'VOYAGE'], false)));
        self::assertSame(['Lampe'], $this->titles(ItemFilter::fromQuery(['tag' => ['Déco']], false)));
        self::assertSame(['Lampe', 'Valise'], $this->titles(ItemFilter::fromQuery(['tag' => ['déco', 'voyage']], false)));
        self::assertSame(['Lampe'], $this->titles(ItemFilter::fromQuery(['prio' => 'high'], false)));
        self::assertSame(['Lampadaire'], $this->titles(ItemFilter::fromQuery([], true)));
        self::assertSame([], $this->titles(ItemFilter::fromQuery(['q' => '%'], false)), 'les jokers SQL sont échappés');
        self::assertSame(['déco'], $this->items->search(ItemFilter::fromQuery(['q' => 'Lampe'], false))[0]['tags']);
        self::assertSame($lamp, $this->items->search(ItemFilter::fromQuery(['q' => 'Lampe'], false))[0]['id']);
    }

    public function testTotals(): void
    {
        self::insertItem($this->alice, ['price_estimated' => '10.00', 'quantity' => 3]);
        self::insertItem($this->alice, ['price_estimated' => '2.50']);
        self::insertItem($this->alice);
        self::insertItem($this->alice, ['is_purchased' => 1, 'purchased_at' => '2026-09-01', 'price_paid' => '42.00']);
        self::insertItem($this->alice, ['is_purchased' => 1, 'purchased_at' => '2026-09-02']);

        self::assertSame(['total' => '32.50', 'priced' => 2, 'unpriced' => 1], $this->items->totals(ItemFilter::fromQuery([], false)));
        self::assertSame(['total' => '42.00', 'priced' => 1, 'unpriced' => 1], $this->items->totals(ItemFilter::fromQuery([], true)));
        self::assertSame(['todo' => 3, 'purchased' => 2], $this->items->counts());
    }

    public function testTotalsOnEmptyList(): void
    {
        self::assertSame(['total' => '0.00', 'priced' => 0, 'unpriced' => 0], $this->items->totals(ItemFilter::fromQuery([], false)));
        self::assertSame(['todo' => 0, 'purchased' => 0], $this->items->counts());
    }

    public function testMarkAndUnmarkPurchased(): void
    {
        $id = $this->items->create($this->data(), $this->alice);

        $this->items->markPurchased($id, '2026-09-20', '15.00', $this->bob);
        $item = $this->items->find($id);
        self::assertSame(1, $item['is_purchased']);
        self::assertSame('2026-09-20', $item['purchased_at']);
        self::assertSame('15.00', $item['price_paid']);
        self::assertSame('Bob', $item['purchased_by_name']);

        $this->items->unmarkPurchased($id);
        $item = $this->items->find($id);
        self::assertSame(0, $item['is_purchased']);
        self::assertNull($item['purchased_at']);
        self::assertNull($item['price_paid']);
        self::assertNull($item['purchased_by']);
    }

    public function testDeleteCascadesAndReturnsImage(): void
    {
        $id = $this->items->create($this->data(['tags' => ['x']]), $this->alice);
        $this->items->setImage($id, str_repeat('a', 32) . '.webp');
        self::pdo()->prepare('INSERT INTO comments (item_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())')->execute([$id, $this->alice, 'hop']);

        self::assertSame(str_repeat('a', 32) . '.webp', $this->items->delete($id));
        self::assertNull($this->items->find($id));
        self::assertSame(0, (int) self::pdo()->query('SELECT COUNT(*) FROM comments')->fetchColumn());
        self::assertSame(0, (int) self::pdo()->query('SELECT COUNT(*) FROM item_tag')->fetchColumn());
        self::assertNull($this->items->delete(9999));
    }
}
