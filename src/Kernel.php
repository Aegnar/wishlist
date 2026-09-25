<?php
declare(strict_types=1);

namespace App;

use App\Http\Request;
use App\Http\Response;

final class Kernel
{
    private const PUBLIC_PATHS = ['/login'];

    private const CSP = "default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; manifest-src 'self'; worker-src 'self'";

    public function __construct(private App $app, private Router $router)
    {
    }

    public function handle(Request $request): Response
    {
        $view = $this->app->view;
        $path = $request->path();
        $view->share('user', null);
        $view->share('currentPath', $path);

        if ($request->method() === 'POST' && !Csrf::validate($request->input('_csrf') ?? $request->header('X-CSRF-Token'))) {
            return $this->error($request, 403, 'Session expirée ou formulaire invalide. Rechargez la page et réessayez.');
        }

        // Partagé après résolution de l'utilisateur : si user() détecte une session expirée et
        // déconnecte, le jeton partagé doit refléter la session (nettoyée) courante, pas l'ancienne.
        $user = $this->app->auth->user();
        $view->share('csrf', Csrf::token());
        if ($user === null) {
            if (in_array($path, self::PUBLIC_PATHS, true)) {
                return $this->dispatch($request);
            }
            return $request->wantsJson()
                ? Response::json(['ok' => false, 'error' => 'Session expirée, reconnectez-vous.'], 401)
                : Response::redirect('/login');
        }

        $view->share('user', $user);
        if (($path === '/admin' || str_starts_with($path, '/admin/')) && $user['role'] !== 'admin') {
            return $this->error($request, 403, "Cette page est réservée à l'administrateur.");
        }
        return $this->dispatch($request);
    }

    public static function secure(Response $response): Response
    {
        return $response
            ->withHeader('Content-Security-Policy', self::CSP)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'same-origin');
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request->method(), $request->path());
        if ($match === null) {
            return $this->error($request, 404, "Cette page n'existe pas.");
        }
        [$handler, $params] = $match;
        return $handler($request, $params);
    }

    private function error(Request $request, int $status, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        return Response::html(
            $this->app->view->render('errors/error', ['title' => "Erreur $status", 'code' => $status, 'message' => $message]),
            $status,
        );
    }
}
