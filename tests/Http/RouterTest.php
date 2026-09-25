<?php
declare(strict_types=1);

namespace Tests\Http;

use App\Http\Request;
use App\Http\Response;
use App\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesStaticAndParameterizedRoutes(): void
    {
        $router = new Router();
        $home = static fn (Request $r, array $p): Response => Response::html('home');
        $show = static fn (Request $r, array $p): Response => Response::html('item ' . $p['id']);
        $media = static fn (Request $r, array $p): Response => Response::html($p['file']);
        $router->get('/', $home);
        $router->get('/item/{id}', $show);
        $router->post('/item/{id}', $show);
        $router->get('/media/{file}', $media);

        self::assertSame($home, $router->match('GET', '/')[0]);
        self::assertSame(['id' => '42'], $router->match('GET', '/item/42')[1]);
        self::assertNotNull($router->match('POST', '/item/42'));
        self::assertSame(['file' => 'abc_t.webp'], $router->match('GET', '/media/abc_t.webp')[1]);
    }

    public function testRejectsUnknownPathsWrongMethodsAndBadParams(): void
    {
        $router = new Router();
        $router->get('/item/{id}', static fn (Request $r, array $p): Response => Response::html(''));
        $router->get('/media/{file}', static fn (Request $r, array $p): Response => Response::html(''));

        self::assertNull($router->match('GET', '/item/abc'));
        self::assertNull($router->match('POST', '/item/1'));
        self::assertNull($router->match('GET', '/item/1/extra'));
        self::assertNull($router->match('GET', '/media/../config.php'));
        self::assertNull($router->match('GET', '/media/..'));
        self::assertNull($router->match('GET', '/nope'));
    }

    public function testRequestNormalizesPathAndReadsInput(): void
    {
        $request = new Request('post', '/item/3/', ['q' => 'x'], ['title' => 'T'], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'tok',
        ]);

        self::assertSame('POST', $request->method());
        self::assertSame('/item/3', $request->path());
        self::assertSame('/', (new Request('GET', '/'))->path());
        self::assertSame('x', $request->query('q'));
        self::assertSame('T', $request->input('title'));
        self::assertSame('d', $request->input('missing', 'd'));
        self::assertSame('10.0.0.1', $request->ip());
        self::assertSame('tok', $request->header('X-CSRF-Token'));
        self::assertTrue($request->wantsJson());
        self::assertNull($request->file('photo'));
    }

    public function testRequestFileIgnoresEmptyUpload(): void
    {
        $empty = new Request('POST', '/', [], [], ['photo' => ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0, 'name' => '']]);
        $real = new Request('POST', '/', [], [], ['photo' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/tmp/x', 'size' => 3, 'name' => 'a.png']]);

        self::assertNull($empty->file('photo'));
        self::assertSame('/tmp/x', $real->file('photo')['tmp_name']);
    }

    public function testResponseFactories(): void
    {
        $redirect = Response::redirect('/login');
        self::assertSame(303, $redirect->status);
        self::assertSame('/login', $redirect->headers['Location']);

        $json = Response::json(['ok' => true, 'msg' => 'été'], 201);
        self::assertSame(201, $json->status);
        self::assertSame('{"ok":true,"msg":"été"}', $json->body);
        self::assertStringStartsWith('application/json', $json->headers['Content-Type']);

        self::assertSame('v', Response::html('x')->withHeader('X-Test', 'v')->headers['X-Test']);
    }
}
