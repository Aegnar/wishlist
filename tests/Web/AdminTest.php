<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class AdminTest extends WebTestCase
{
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('root', 'admin');
        $this->loginAs($this->admin);
    }

    private function newUser(array $overrides = []): \App\Http\Response
    {
        return $this->request('POST', '/admin/users', $overrides + [
            'username' => 'marie',
            'display_name' => 'Marie',
            'role' => 'user',
            'password' => 'mot-de-passe-marie',
            'password_confirm' => 'mot-de-passe-marie',
        ]);
    }

    public function testIndexListsUsersAndTags(): void
    {
        $this->createItem($this->admin['id'], ['tags' => ['meuble']]);

        $body = $this->request('GET', '/admin')->body;

        self::assertStringContainsString('Administration', $body);
        self::assertStringContainsString('Root', $body);
        self::assertStringContainsString('meuble', $body);
    }

    public function testCreateUser(): void
    {
        $response = $this->newUser(['username' => ' Marie ']);

        self::assertSame('/admin', $response->headers['Location']);
        $marie = $this->app->users->findByUsername('marie');
        self::assertSame('marie', $marie['username']);
        self::assertSame('user', $marie['role']);
        self::assertNotNull($this->app->auth->attempt('marie', 'mot-de-passe-marie', '10.0.0.1'));
    }

    public function testCreateUserValidation(): void
    {
        $this->newUser();

        $duplicate = $this->newUser();
        self::assertSame(422, $duplicate->status);
        self::assertStringContainsString('déjà utilisé', $duplicate->body);

        $weak = $this->newUser(['username' => 'paul', 'password' => 'court', 'password_confirm' => 'court']);
        self::assertSame(422, $weak->status);
        self::assertStringContainsString('au moins 10 caractères', $weak->body);
        self::assertStringContainsString('value="paul"', $weak->body);
    }

    public function testCannotDeactivateOwnAccount(): void
    {
        $this->request('POST', "/admin/users/{$this->admin['id']}/toggle");

        self::assertSame(1, $this->app->users->find($this->admin['id'])['is_active']);
        self::assertSame('error', $_SESSION['_flash'][0]['type']);
    }

    public function testToggleOtherUser(): void
    {
        $marie = $this->createUser('marie');

        $this->request('POST', "/admin/users/{$marie['id']}/toggle");
        self::assertSame(0, $this->app->users->find($marie['id'])['is_active']);

        $this->request('POST', "/admin/users/{$marie['id']}/toggle");
        self::assertSame(1, $this->app->users->find($marie['id'])['is_active']);
    }

    public function testResetPassword(): void
    {
        $marie = $this->createUser('marie');

        $this->request('POST', "/admin/users/{$marie['id']}/password", ['password' => 'provisoire-123', 'password_confirm' => 'provisoire-123']);
        self::assertTrue(password_verify('provisoire-123', $this->app->users->find($marie['id'])['password_hash']));

        $this->request('POST', "/admin/users/{$marie['id']}/password", ['password' => 'x', 'password_confirm' => 'x']);
        self::assertTrue(password_verify('provisoire-123', $this->app->users->find($marie['id'])['password_hash']));
    }

    public function testResettingOwnPasswordKeepsAdminLoggedIn(): void
    {
        $this->request('POST', "/admin/users/{$this->admin['id']}/password", ['password' => 'provisoire-123', 'password_confirm' => 'provisoire-123']);
        $this->newRequestCycle();

        self::assertSame(200, $this->request('GET', '/admin')->status);
    }

    public function testTagManagement(): void
    {
        $itemA = $this->createItem($this->admin['id'], ['tags' => ['meubles']]);
        $itemB = $this->createItem($this->admin['id'], ['tags' => ['meuble']]);
        $plural = $this->app->tags->findByName('meubles')['id'];
        $singular = $this->app->tags->findByName('meuble')['id'];
        $unused = $this->app->tags->findOrCreate('libre');

        $this->request('POST', "/admin/tags/$plural/rename", ['name' => 'Meuble']);
        self::assertSame('error', $_SESSION['_flash'][0]['type'], 'renommer vers un nom existant est refusé');
        unset($_SESSION['_flash']);

        $this->request('POST', "/admin/tags/$plural/delete");
        self::assertNotNull($this->app->tags->find($plural), 'tag utilisé non supprimé');

        $this->request('POST', "/admin/tags/$plural/merge", ['into' => (string) $singular]);
        self::assertNull($this->app->tags->find($plural));
        self::assertSame(['meuble'], $this->app->items->find($itemA)['tags']);

        $this->request('POST', "/admin/tags/$singular/rename", ['name' => 'Mobilier']);
        self::assertSame(['Mobilier'], $this->app->items->find($itemB)['tags']);

        $this->request('POST', "/admin/tags/$unused/delete");
        self::assertNull($this->app->tags->find($unused));
    }

    public function testRegularUserCannotPostToAdmin(): void
    {
        $this->loginAs($this->createUser('alice'));

        self::assertSame(403, $this->newUser()->status);
        self::assertNull($this->app->users->findByUsername('marie'));
    }
}
