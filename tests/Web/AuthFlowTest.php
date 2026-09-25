<?php
declare(strict_types=1);

namespace Tests\Web;

use App\Auth;
use App\Http\Response;
use App\Kernel;
use Tests\WebTestCase;

final class AuthFlowTest extends WebTestCase
{
    public function testGuestIsRedirectedToLogin(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(303, $response->status);
        self::assertSame('/login', $response->headers['Location']);
    }

    public function testGuestJsonRequestGets401(): void
    {
        $response = $this->request('GET', '/tags/suggest?q=a', headers: ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(401, $response->status);
        self::assertStringContainsString('"ok":false', $response->body);
    }

    public function testLoginPageRenders(): void
    {
        $response = $this->request('GET', '/login');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Se connecter', $response->body);
        self::assertStringContainsString('name="_csrf"', $response->body);
        self::assertStringNotContainsString('Déconnexion', $response->body);
    }

    public function testLoginSuccess(): void
    {
        $user = $this->createUser('alice');

        $response = $this->request('POST', '/login', ['username' => 'alice', 'password' => 'motdepasse-solide', 'remember' => '1']);

        self::assertSame(303, $response->status);
        self::assertSame('/', $response->headers['Location']);
        self::assertSame($user['id'], $_SESSION['user_id']);
        self::assertTrue($_SESSION['remember']);
    }

    public function testLoginFailure(): void
    {
        $this->createUser('alice');

        $response = $this->request('POST', '/login', ['username' => 'alice', 'password' => 'mauvais']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Identifiants invalides.', $response->body);
        self::assertStringContainsString('value="alice"', $response->body);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testPostWithoutValidCsrfIsRejected(): void
    {
        $this->createUser('alice');

        $response = $this->request('POST', '/login', ['_csrf' => 'faux', 'username' => 'alice', 'password' => 'motdepasse-solide']);

        self::assertSame(403, $response->status);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testRateLimitAfterRepeatedFailures(): void
    {
        $this->createUser('alice');
        for ($i = 0; $i < Auth::MAX_ATTEMPTS; $i++) {
            $this->request('POST', '/login', ['username' => 'alice', 'password' => 'mauvais']);
        }

        $response = $this->request('POST', '/login', ['username' => 'alice', 'password' => 'motdepasse-solide']);

        self::assertSame(429, $response->status);
        self::assertStringContainsString('Trop de tentatives', $response->body);
    }

    public function testLoggedInUserSeesLayoutAndUnknownRouteIs404(): void
    {
        $this->loginAs($this->createUser('alice'));

        $response = $this->request('GET', '/nope');

        self::assertSame(404, $response->status);
        self::assertStringContainsString('Déconnexion', $response->body);
        self::assertStringContainsString(e("Cette page n'existe pas."), $response->body);
    }

    public function testAdminAreaIsForbiddenForUsers(): void
    {
        $this->loginAs($this->createUser('alice'));

        self::assertSame(403, $this->request('GET', '/admin')->status);
    }

    public function testLoginPageRedirectsWhenLoggedIn(): void
    {
        $this->loginAs($this->createUser('alice'));

        self::assertSame('/', $this->request('GET', '/login')->headers['Location']);
    }

    public function testLogout(): void
    {
        $this->loginAs($this->createUser('alice'));

        $response = $this->request('POST', '/logout');

        self::assertSame('/login', $response->headers['Location']);
        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testSecurityHeaders(): void
    {
        $response = Kernel::secure(Response::html('x'));

        self::assertStringContainsString("script-src 'self'", $response->headers['Content-Security-Policy']);
        self::assertStringContainsString("frame-ancestors 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers['X-Frame-Options']);
        self::assertSame('same-origin', $response->headers['Referrer-Policy']);
    }
}
