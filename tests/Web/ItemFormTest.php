<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class ItemFormTest extends WebTestCase
{
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice');
        $this->loginAs($this->alice);
    }

    /** @return array{name: string, tmp_name: string, error: int, size: int} */
    private function uploadedPng(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        $img = imagecreatetruecolor(30, 20);
        imagepng($img, $tmp);
        return ['name' => 'photo.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($tmp)];
    }

    private function uploadsCount(): int
    {
        return count(glob($this->storage . '/uploads/*.webp') ?: []);
    }

    public function testNewFormRenders(): void
    {
        $response = $this->request('GET', '/item/new');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Ajouter un produit', $response->body);
        self::assertStringContainsString('enctype="multipart/form-data"', $response->body);
        self::assertStringContainsString('tags.js', $response->body);
    }

    public function testCreateItem(): void
    {
        $response = $this->request('POST', '/item', [
            'title' => 'Canapé',
            'url' => 'https://exemple.test/canape',
            'store' => 'IKEA',
            'price_estimated' => '499,90',
            'quantity' => '1',
            'priority' => 'high',
            'tags' => 'meuble, salon',
            'description' => 'Gris clair',
        ]);

        self::assertSame(303, $response->status);
        self::assertMatchesRegularExpression('#^/item/\d+$#', $response->headers['Location']);
        $item = $this->app->items->find((int) basename($response->headers['Location']));
        self::assertSame('Canapé', $item['title']);
        self::assertSame('499.90', $item['price_estimated']);
        self::assertSame(['meuble', 'salon'], $item['tags']);
        self::assertSame($this->alice['id'], $item['created_by']);
        self::assertSame([['type' => 'success', 'message' => 'Produit ajouté.']], $_SESSION['_flash']);
    }

    public function testInvalidInputRerendersFormWithValues(): void
    {
        $response = $this->request('POST', '/item', ['title' => '', 'store' => 'Leroy', 'price_estimated' => 'abc']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Le titre est obligatoire.', $response->body);
        self::assertStringContainsString('Prix invalide', $response->body);
        self::assertStringContainsString('value="Leroy"', $response->body);
        self::assertSame(['todo' => 0, 'purchased' => 0], $this->app->items->counts());
    }

    public function testCreateWithUploadedPhoto(): void
    {
        $response = $this->request('POST', '/item', ['title' => 'Lampe'], ['photo' => $this->uploadedPng()]);

        $item = $this->app->items->find((int) basename($response->headers['Location']));
        self::assertNotNull($item['image_path']);
        self::assertFileExists($this->storage . '/uploads/' . $item['image_path']);
        self::assertSame(2, $this->uploadsCount(), 'photo + miniature');
    }

    public function testInvalidPhotoKeepsFormAndCreatesNothing(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, 'pas une image');

        $response = $this->request('POST', '/item', ['title' => 'Lampe'], [
            'photo' => ['name' => 'x.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 13],
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Format non supporté', $response->body);
        self::assertStringContainsString('value="Lampe"', $response->body);
        self::assertSame(['todo' => 0, 'purchased' => 0], $this->app->items->counts());
    }

    public function testPrivateImageUrlIsRefused(): void
    {
        $response = $this->request('POST', '/item', ['title' => 'Lampe', 'image_url' => 'http://127.0.0.1/photo.png']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('non autorisée', $response->body);
    }

    public function testEditFormIsPrefilled(): void
    {
        $id = $this->createItem($this->alice['id'], ['title' => 'Lampe', 'price_estimated' => '12.50', 'tags' => ['déco', 'salon']]);

        $body = $this->request('GET', "/item/$id/edit")->body;

        self::assertStringContainsString('Modifier le produit', $body);
        self::assertStringContainsString('value="12,50"', $body);
        self::assertStringContainsString('value="déco, salon"', $body);
        self::assertStringContainsString('action="/item/' . $id . '"', $body);
    }

    public function testUpdateItemAndReplacePhoto(): void
    {
        $id = $this->createItem($this->alice['id'], ['title' => 'Lampe', 'tags' => ['a']]);
        $this->request('POST', "/item/$id", ['title' => 'Lampe'], ['photo' => $this->uploadedPng()]);
        $firstImage = $this->app->items->find($id)['image_path'];

        $response = $this->request('POST', "/item/$id", ['title' => 'Lampe LED', 'tags' => 'b', 'priority' => 'low'], ['photo' => $this->uploadedPng()]);

        self::assertSame("/item/$id", $response->headers['Location']);
        $item = $this->app->items->find($id);
        self::assertSame('Lampe LED', $item['title']);
        self::assertSame(['b'], $item['tags']);
        self::assertSame('low', $item['priority']);
        self::assertNotSame($firstImage, $item['image_path']);
        self::assertFileDoesNotExist($this->storage . '/uploads/' . $firstImage);
        self::assertSame(2, $this->uploadsCount());
    }

    public function testRemovePhoto(): void
    {
        $id = $this->createItem($this->alice['id'], ['title' => 'Lampe']);
        $this->request('POST', "/item/$id", ['title' => 'Lampe'], ['photo' => $this->uploadedPng()]);

        $this->request('POST', "/item/$id", ['title' => 'Lampe', 'remove_photo' => '1']);

        self::assertNull($this->app->items->find($id)['image_path']);
        self::assertSame(0, $this->uploadsCount());
    }

    public function testEditMissingItemIs404(): void
    {
        self::assertSame(404, $this->request('GET', '/item/9999/edit')->status);
        self::assertSame(404, $this->request('POST', '/item/9999', ['title' => 'x'])->status);
    }

    public function testTagSuggestions(): void
    {
        $this->createItem($this->alice['id'], ['tags' => ['Commun', 'Mural', 'Déco']]);

        $response = $this->request('GET', '/tags/suggest?q=mu', headers: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(200, $response->status);
        self::assertSame(['ok' => true, 'tags' => ['Mural', 'Commun']], json_decode($response->body, true));
        self::assertSame(['ok' => true, 'tags' => []], json_decode($this->request('GET', '/tags/suggest?q=')->body, true));
    }
}
