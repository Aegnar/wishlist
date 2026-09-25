<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

final class TagController extends Controller
{
    public function suggest(Request $request, array $params): Response
    {
        $q = $request->query('q', '');
        $q = is_string($q) ? trim($q) : '';
        return Response::json(['ok' => true, 'tags' => $q === '' ? [] : $this->app->tags->suggest($q)]);
    }
}
