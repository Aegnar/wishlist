<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class ItemPagesTest extends WebTestCase
{
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice');
        $this->loginAs($this->alice);
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    public function testEmptyList(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('La liste est vide', $response->body);
    }

    public function testListShowsTodoItemsWithTotals(): void
    {
        $this->createItem($this->alice['id'], ['title' => 'Canapé', 'price_estimated' => '400.00', 'quantity' => 2, 'priority' => 'high', 'tags' => ['meuble']]);
        $this->createItem($this->alice['id'], ['title' => 'Stylo']);
        $bought = $this->createItem($this->alice['id'], ['title' => 'Lampe']);
        $this->app->items->markPurchased($bought, '2026-09-01', '30.00', $this->alice['id']);

        $body = $this->request('GET', '/')->body;

        self::assertStringContainsString('Canapé', $body);
        self::assertStringContainsString('Stylo', $body);
        self::assertStringNotContainsString('Lampe', $body);
        self::assertStringContainsString(e(format_price('800.00')), $body);
        self::assertStringContainsString('1 produit sans prix', $body);
        self::assertStringContainsString('badge-high', $body);
        self::assertStringContainsString('value="meuble"', $body, 'le tag est proposé dans le filtre');
    }

    public function testPurchasedTabAndFilters(): void
    {
        $this->createItem($this->alice['id'], ['title' => 'Lampe de bureau']);
        $this->createItem($this->alice['id'], ['title' => 'Valise']);
        $bought = $this->createItem($this->alice['id'], ['title' => 'Chaise']);
        $this->app->items->markPurchased($bought, '2026-09-01', '30.00', $this->alice['id']);

        $filtered = $this->request('GET', '/?q=lampe')->body;
        self::assertStringContainsString('Lampe de bureau', $filtered);
        self::assertStringNotContainsString('Valise', $filtered);
        self::assertStringContainsString('Réinitialiser', $filtered);

        $purchased = $this->request('GET', '/purchased')->body;
        self::assertStringContainsString('Chaise', $purchased);
        self::assertStringContainsString('Total dépensé', $purchased);
        self::assertStringNotContainsString('Valise', $purchased);
    }

    public function testOutputIsEscaped(): void
    {
        $id = $this->createItem($this->alice['id'], ['title' => '<script>alert(1)</script>', 'description' => '<b>gras</b>']);

        $list = $this->request('GET', '/')->body;
        $show = $this->request('GET', "/item/$id")->body;

        self::assertStringNotContainsString('<script>alert(1)</script>', $list);
        self::assertStringContainsString('&lt;script&gt;', $list);
        self::assertStringNotContainsString('<b>gras</b>', $show);
    }

    public function testShowPage(): void
    {
        $id = $this->createItem($this->alice['id'], [
            'title' => 'Canapé',
            'url' => 'https://exemple.test/canape',
            'price_estimated' => '12.50',
            'quantity' => 2,
            'tags' => ['salon'],
        ]);

        $response = $this->request('GET', "/item/$id");

        self::assertSame(200, $response->status);
        self::assertStringContainsString('<h1>Canapé</h1>', $response->body);
        self::assertStringContainsString('rel="noopener noreferrer"', $response->body);
        self::assertStringContainsString('Marquer acheté', $response->body);
        self::assertStringContainsString('value="25,00"', $response->body, 'prix payé prérempli = prix × quantité');
        self::assertStringContainsString('Aucun commentaire', $response->body);
        self::assertStringContainsString('Ajouté par Alice', $response->body);
    }

    public function testShowMissingItemIs404(): void
    {
        self::assertSame(404, $this->request('GET', '/item/9999')->status);
    }

    public function testMediaServesStoredImagesOnly(): void
    {
        $name = $this->app->images->storeBytes($this->png());

        $response = $this->request('GET', '/media/' . $name);
        self::assertSame(200, $response->status);
        self::assertSame('image/webp', $response->headers['Content-Type']);
        self::assertSame($this->storage . '/uploads/' . $name, $response->file);

        self::assertSame(200, $this->request('GET', '/media/' . str_replace('.webp', '_t.webp', $name))->status);
        self::assertSame(404, $this->request('GET', '/media/' . str_repeat('0', 32) . '.webp')->status);
        self::assertSame(404, $this->request('GET', '/media/config.php')->status);
    }

    public function testMediaRequiresLogin(): void
    {
        $name = $this->app->images->storeBytes($this->png());
        $this->app->auth->logout();

        self::assertSame(303, $this->request('GET', '/media/' . $name)->status);
    }
}
