<?php
declare(strict_types=1);

namespace Tests\Web;

use App\Router;
use ReflectionProperty;
use Tests\WebTestCase;

/** Garde-fous transverses : chaque route de src/routes.php est protégée (connexion, CSRF, admin). */
final class RouteSecurityTest extends WebTestCase
{
    /** Toutes les routes de src/routes.php, avec des paramètres d'exemple (id = 1). */
    private const ROUTES = [
        ['GET', '/login'],
        ['POST', '/login'],
        ['POST', '/logout'],
        ['GET', '/'],
        ['GET', '/purchased'],
        ['GET', '/item/1'],
        ['GET', '/item/new'],
        ['POST', '/item'],
        ['GET', '/item/1/edit'],
        ['POST', '/item/1'],
        ['POST', '/item/1/purchase'],
        ['POST', '/item/1/unpurchase'],
        ['POST', '/item/1/delete'],
        ['GET', '/tags/suggest'],
        ['GET', '/media/0123456789abcdef0123456789abcdef.webp'],
        ['POST', '/item/1/comments'],
        ['POST', '/comments/1/delete'],
        ['GET', '/account'],
        ['POST', '/account'],
        ['GET', '/admin'],
        ['POST', '/admin/users'],
        ['POST', '/admin/users/1/password'],
        ['POST', '/admin/users/1/toggle'],
        ['POST', '/admin/tags/1/rename'],
        ['POST', '/admin/tags/1/merge'],
        ['POST', '/admin/tags/1/delete'],
    ];

    public function testRouteListMatchesRoutesFile(): void
    {
        $router = new Router();
        (require dirname(__DIR__, 2) . '/src/routes.php')($router, $this->app);

        self::assertCount(count(self::ROUTES), (new ReflectionProperty(Router::class, 'routes'))->getValue($router), 'route ajoutée ou retirée : mettre à jour ROUTES');
        foreach (self::ROUTES as [$method, $uri]) {
            self::assertNotNull($router->match($method, $uri), "$method $uri");
        }
    }

    // Une boucle par propriété plutôt qu'un DataProvider : chaque cas de WebTestCase vide la base,
    // ce qui rendrait la suite nettement plus lente sans rien vérifier de plus.
    public function testLoggedOutRequestsAreRedirectedToLogin(): void
    {
        foreach (self::ROUTES as [$method, $uri]) {
            if ($uri === '/login') {
                continue;
            }
            $response = $this->request($method, $uri); // POST : jeton CSRF valide ajouté par request()

            self::assertSame(303, $response->status, "$method $uri");
            self::assertSame('/login', $response->headers['Location'], "$method $uri");
        }
    }

    public function testPostWithoutValidCsrfTokenIsRejectedWhenLoggedIn(): void
    {
        $this->loginAs($this->createUser('root', 'admin'));

        foreach (self::ROUTES as [$method, $uri]) {
            if ($method !== 'POST') {
                continue;
            }
            self::assertSame(403, $this->request('POST', $uri, ['_csrf' => null])->status, "$uri sans jeton");
            self::assertSame(403, $this->request('POST', $uri, ['_csrf' => str_repeat('0', 64)])->status, "$uri jeton faux");
        }
        self::assertArrayHasKey('user_id', $_SESSION, 'POST /logout rejeté : toujours connecté');
    }

    public function testAdminRoutesAreForbiddenForRegularUsers(): void
    {
        $this->loginAs($this->createUser('alice'));

        foreach (self::ROUTES as [$method, $uri]) {
            if (str_starts_with($uri, '/admin')) {
                self::assertSame(403, $this->request($method, $uri)->status, "$method $uri");
            }
        }
    }
}
