<?php
declare(strict_types=1);

namespace Tests;

use App\ItemFilter;
use PHPUnit\Framework\TestCase;

final class ItemFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $todo = ItemFilter::fromQuery([], false);
        $done = ItemFilter::fromQuery([], true);

        self::assertSame('priority', $todo->sort);
        self::assertSame('purchased_desc', $done->sort);
        self::assertFalse($todo->isActive());
        self::assertSame([], $todo->toQuery());
    }

    public function testParsesAndSanitizesQuery(): void
    {
        $filter = ItemFilter::fromQuery([
            'q' => '  lampe ',
            'tag' => ['Meuble', 'meuble', '', ['x']],
            'prio' => 'high',
            'sort' => 'price_desc',
        ], false);

        self::assertSame('lampe', $filter->q);
        self::assertSame(['Meuble'], $filter->tags);
        self::assertSame('high', $filter->priority);
        self::assertSame('price_desc', $filter->sort);
        self::assertTrue($filter->isActive());
        self::assertSame(['q' => 'lampe', 'tag' => ['Meuble'], 'prio' => 'high', 'sort' => 'price_desc'], $filter->toQuery());
    }

    public function testRejectsInvalidValues(): void
    {
        $filter = ItemFilter::fromQuery(['tag' => 'Solo', 'prio' => 'urgent', 'sort' => 'purchased_desc', 'q' => ['x']], false);

        self::assertSame(['Solo'], $filter->tags);
        self::assertNull($filter->priority);
        self::assertSame('priority', $filter->sort, 'tri réservé à l\'onglet Achetés');
        self::assertSame('', $filter->q);
    }
}
