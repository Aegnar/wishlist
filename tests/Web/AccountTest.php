<?php
declare(strict_types=1);

namespace Tests\Web;

use Tests\WebTestCase;

final class AccountTest extends WebTestCase
{
    private array $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice');
        $this->loginAs($this->alice);
    }

    public function testShowsAccountPage(): void
    {
        $body = $this->request('GET', '/account')->body;

        self::assertStringContainsString('Changer de mot de passe', $body);
        self::assertStringContainsString('alice', $body);
    }

    public function testWrongCurrentPasswordIsRejected(): void
    {
        $response = $this->request('POST', '/account', [
            'current_password' => 'mauvais',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirm' => 'nouveau-mot-de-passe',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Mot de passe actuel incorrect.', $response->body);
    }

    public function testMismatchIsRejected(): void
    {
        $response = $this->request('POST', '/account', [
            'current_password' => 'motdepasse-solide',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirm' => 'autre-mot-de-passe',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Les mots de passe ne correspondent pas.', $response->body);
    }

    public function testChangePassword(): void
    {
        $response = $this->request('POST', '/account', [
            'current_password' => 'motdepasse-solide',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirm' => 'nouveau-mot-de-passe',
        ]);

        self::assertSame('/account', $response->headers['Location']);
        self::assertTrue(password_verify('nouveau-mot-de-passe', $this->app->users->find($this->alice['id'])['password_hash']));
    }

    public function testChangingOwnPasswordKeepsThisDeviceLoggedIn(): void
    {
        $this->request('POST', '/account', [
            'current_password' => 'motdepasse-solide',
            'new_password' => 'nouveau-mot-de-passe',
            'new_password_confirm' => 'nouveau-mot-de-passe',
        ]);
        $this->newRequestCycle();

        self::assertSame(200, $this->request('GET', '/account')->status);
    }

    public function testPasswordResetElsewhereLogsThisDeviceOut(): void
    {
        self::assertSame(200, $this->request('GET', '/account')->status);

        $this->app->users->updatePassword($this->alice['id'], 'mot-de-passe-provisoire');
        $this->newRequestCycle();
        $response = $this->request('GET', '/account');

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
    }
}
