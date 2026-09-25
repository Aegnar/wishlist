<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

final class MediaController extends Controller
{
    /** Photos servies uniquement aux utilisateurs connectés (contrôle fait par le Kernel). */
    public function show(Request $request, array $params): Response
    {
        $path = $this->app->images->path($params['file']);
        return $path === null ? $this->notFound() : Response::file($path, 'image/webp');
    }
}
