<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class ItemPurchaseTest extends WebTestCase
{
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice');
        $this->loginAs($this->alice);
    }

    public function testMarkPurchased(): void
    {
        $id = $this->createItem($this->alice['id'], ['title' => 'Lampe']);

        $response = $this->request('POST', "/item/$id/purchase", ['purchased_at' => date('Y-m-d'), 'price_paid' => '19,90']);

        self::assertSame("/item/$id", $response->headers['Location']);
        $item = $this->app->items->find($id);
        self::assertSame(1, $item['is_purchased']);
        self::assertSame(date('Y-m-d'), $item['purchased_at']);
        self::assertSame('19.90', $item['price_paid']);
        self::assertSame($this->alice['id'], $item['purchased_by']);

        $page = $this->request('GET', "/item/$id")->body;
        self::assertStringContainsString('Remettre à acheter', $page);
        self::assertStringNotContainsString('purchase-dialog', $page);
    }

    public function testInvalidPurchaseReopensDialogWithErrors(): void
    {
        $id = $this->createItem($this->alice['id']);
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $response = $this->request('POST', "/item/$id/purchase", ['purchased_at' => $tomorrow, 'price_paid' => 'x']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('data-open-on-load', $response->body);
        self::assertStringContainsString('ne peut pas être dans le futur', $response->body);
        self::assertStringContainsString('value="' . $tomorrow . '"', $response->body);
        self::assertSame(0, $this->app->items->find($id)['is_purchased']);
    }

    public function testUnpurchase(): void
    {
        $id = $this->createItem($this->alice['id']);
        $this->app->items->markPurchased($id, '2026-09-01', '10.00', $this->alice['id']);

        $response = $this->request('POST', "/item/$id/unpurchase");

        self::assertSame("/item/$id", $response->headers['Location']);
        self::assertSame(0, $this->app->items->find($id)['is_purchased']);
    }

    public function testDeleteRemovesItemAndPhoto(): void
    {
        $id = $this->createItem($this->alice['id']);
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);
        $name = $this->app->images->storeBytes((string) ob_get_clean());
        $this->app->items->setImage($id, $name);

        $response = $this->request('POST', "/item/$id/delete");

        self::assertSame('/', $response->headers['Location']);
        self::assertNull($this->app->items->find($id));
        self::assertSame([], glob($this->storage . '/uploads/*'));
    }

    public function testDeletePurchasedItemRedirectsToPurchasedTab(): void
    {
        $id = $this->createItem($this->alice['id']);
        $this->app->items->markPurchased($id, '2026-09-01', null, $this->alice['id']);

        self::assertSame('/purchased', $this->request('POST', "/item/$id/delete")->headers['Location']);
    }

    public function testMissingItemIs404(): void
    {
        self::assertSame(404, $this->request('POST', '/item/9999/purchase', ['purchased_at' => date('Y-m-d')])->status);
        self::assertSame(404, $this->request('POST', '/item/9999/unpurchase')->status);
        self::assertSame(404, $this->request('POST', '/item/9999/delete')->status);
    }
}
