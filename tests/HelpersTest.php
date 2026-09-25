<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testFormatPrice(): void
    {
        self::assertSame("1\u{202F}234,50\u{00A0}€", format_price('1234.5'));
        self::assertSame("0,00\u{00A0}€", format_price(0));
        self::assertSame('—', format_price(null));
        self::assertSame('—', format_price(''));
    }

    public function testFormatPriceInput(): void
    {
        self::assertSame('12,50', format_price_input('12.5'));
        self::assertSame('1234,00', format_price_input(1234));
        self::assertSame('', format_price_input(null));
    }

    public function testDates(): void
    {
        self::assertSame('25/09/2026', format_date('2026-09-25'));
        self::assertSame('25/09/2026 à 14:05', format_datetime('2026-09-25 14:05:33'));
        self::assertSame('—', format_date(null));
    }

    public function testPriorityLabel(): void
    {
        self::assertSame('Haute', priority_label('high'));
        self::assertSame('Aucune', priority_label('none'));
        self::assertSame('Basse', priority_label('low'));
    }

    public function testUrlBuilderDropsEmptyValues(): void
    {
        self::assertSame('/', url('/', ['q' => '', 'prio' => null, 'tag' => []]));
        self::assertSame('/?q=lampe&prio=high', url('/', ['q' => 'lampe', 'prio' => 'high']));
        self::assertSame('/?tag%5B0%5D=a+b', url('/', ['tag' => ['a b']]));
    }

    public function testMediaUrl(): void
    {
        $name = str_repeat('a', 32) . '.webp';
        self::assertSame('/media/' . $name, media_url($name));
        self::assertSame('/media/' . str_repeat('a', 32) . '_t.webp', media_url($name, true));
    }

    public function testAssetWithoutFileHasNoVersion(): void
    {
        self::assertSame('/assets/missing.css', asset('missing.css'));
    }
}
