<?php
declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Flash;
use App\Http\Request;
use App\Http\Response;
use LogicException;

abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data), $status);
    }

    protected function redirect(string $location): Response
    {
        return Response::redirect($location);
    }

    /** Utilisateur connecté (garanti par le Kernel sur les routes protégées). */
    protected function user(): array
    {
        return $this->app->auth->user() ?? throw new LogicException('Utilisateur non connecté');
    }

    protected function notFound(): Response
    {
        return $this->render('errors/error', ['title' => 'Introuvable', 'code' => 404, 'message' => "Cette page n'existe pas (ou plus)."], 404);
    }

    protected function forbidden(string $message): Response
    {
        return $this->render('errors/error', ['title' => 'Accès refusé', 'code' => 403, 'message' => $message], 403);
    }

    /** @param 'success'|'error'|'info' $type */
    protected function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }

    /** Valeur texte d'un champ POST ('' si absent ou non textuel). */
    protected function text(Request $request, string $key): string
    {
        $value = $request->input($key, '');
        return is_string($value) ? $value : '';
    }
}
