<?php
declare(strict_types=1);

namespace Tests;

use App\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidItemIsNormalized(): void
    {
        $result = Validator::item([
            'title' => "  Canapé 3 places \n",
            'description' => "Ligne 1\r\nLigne 2  ",
            'url' => 'https://exemple.test/canape',
            'store' => ' IKEA ',
            'price_estimated' => '1 299,9 €',
            'quantity' => '2',
            'priority' => 'high',
            'tags' => 'Meuble, salon ,meuble,, Déco',
            'image_url' => '',
        ]);

        self::assertSame([], $result['errors']);
        self::assertSame([
            'title' => 'Canapé 3 places',
            'description' => "Ligne 1\nLigne 2",
            'url' => 'https://exemple.test/canape',
            'store' => 'IKEA',
            'price_estimated' => '1299.90',
            'quantity' => 2,
            'priority' => 'high',
            'tags' => ['Meuble', 'salon', 'Déco'],
            'image_url' => null,
        ], $result['data']);
    }

    public function testDefaultsForOptionalFields(): void
    {
        $result = Validator::item(['title' => 'Stylo']);

        self::assertSame([], $result['errors']);
        self::assertNull($result['data']['description']);
        self::assertNull($result['data']['url']);
        self::assertNull($result['data']['price_estimated']);
        self::assertSame(1, $result['data']['quantity']);
        self::assertSame('none', $result['data']['priority']);
        self::assertSame([], $result['data']['tags']);
    }

    public function testInvalidItem(): void
    {
        $errors = Validator::item([
            'title' => '',
            'url' => 'javascript:alert(1)',
            'price_estimated' => 'abc',
            'quantity' => '0',
            'priority' => 'urgent',
            'image_url' => 'ftp://exemple.test/a.png',
        ])['errors'];

        self::assertSame(['title', 'url', 'price_estimated', 'quantity', 'priority', 'image_url'], array_keys($errors));
        self::assertSame('Le titre est obligatoire.', $errors['title']);
    }

    public function testTitleTooLong(): void
    {
        self::assertArrayHasKey('title', Validator::item(['title' => str_repeat('a', 201)])['errors']);
    }

    public function testParsePrice(): void
    {
        self::assertSame('12.50', Validator::parsePrice('12,5'));
        self::assertSame('12.50', Validator::parsePrice('12.50'));
        self::assertSame('0.00', Validator::parsePrice('0'));
        self::assertSame('9999999.99', Validator::parsePrice('9999999,99'));
        self::assertNull(Validator::parsePrice(''));
        self::assertNull(Validator::parsePrice(null));
        self::assertFalse(Validator::parsePrice('-3'));
        self::assertFalse(Validator::parsePrice('12,345'));
        self::assertFalse(Validator::parsePrice('10000000'));
        self::assertFalse(Validator::parsePrice(['1']));
    }

    public function testTags(): void
    {
        self::assertSame('Salle de bain', Validator::tagName("  Salle \t de   bain "));
        self::assertSame(50, mb_strlen(Validator::tagName(str_repeat('é', 60))));
        self::assertSame(['a', 'B'], Validator::tagList(['a', 'A', ' B ', 3]));
        self::assertArrayHasKey('tags', Validator::item(['title' => 'x', 'tags' => implode(',', range(1, 21))])['errors']);
    }

    public function testPurchase(): void
    {
        $ok = Validator::purchase(['purchased_at' => '2026-09-20', 'price_paid' => '15,00'], '2026-09-25');
        self::assertSame([], $ok['errors']);
        self::assertSame(['purchased_at' => '2026-09-20', 'price_paid' => '15.00'], $ok['data']);

        self::assertSame([], Validator::purchase(['purchased_at' => '2026-09-25', 'price_paid' => ''], '2026-09-25')['errors']);
        self::assertArrayHasKey('purchased_at', Validator::purchase(['purchased_at' => '2026-09-26'], '2026-09-25')['errors']);
        self::assertArrayHasKey('purchased_at', Validator::purchase(['purchased_at' => '2026-02-30'], '2026-09-25')['errors']);
        self::assertArrayHasKey('purchased_at', Validator::purchase([], '2026-09-25')['errors']);
        self::assertArrayHasKey('price_paid', Validator::purchase(['purchased_at' => '2026-09-20', 'price_paid' => 'x'], '2026-09-25')['errors']);
    }

    public function testComment(): void
    {
        self::assertSame(['body' => "a\nb"], Validator::comment("  a\r\nb ")['data']);
        self::assertArrayHasKey('body', Validator::comment('   ')['errors']);
        self::assertArrayHasKey('body', Validator::comment(str_repeat('x', 2001))['errors']);
        self::assertArrayHasKey('body', Validator::comment(['x'])['errors']);
    }

    public function testAccountFields(): void
    {
        self::assertNull(Validator::username('marie.d'));
        self::assertNotNull(Validator::username('ab'));
        self::assertNotNull(Validator::username('Marie'));
        self::assertNotNull(Validator::username('marie d'));

        self::assertNull(Validator::displayName('Marie'));
        self::assertNotNull(Validator::displayName('  '));

        self::assertNull(Validator::password('0123456789', '0123456789'));
        self::assertSame('Le mot de passe doit contenir au moins 10 caractères.', Validator::password('court', 'court'));
        self::assertSame('Les mots de passe ne correspondent pas.', Validator::password('0123456789', '0123456780'));
    }
}
