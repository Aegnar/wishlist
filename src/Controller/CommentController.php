<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Validator;

final class CommentController extends Controller
{
    public function store(Request $request, array $params): Response
    {
        $itemId = (int) $params['id'];
        if ($this->app->items->find($itemId) === null) {
            return $request->wantsJson()
                ? Response::json(['ok' => false, 'error' => 'Produit introuvable.'], 404)
                : $this->notFound();
        }
        $result = Validator::comment($request->input('body', ''));
        if ($result['errors'] !== []) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => $result['errors']['body']], 422);
            }
            $this->flash('error', $result['errors']['body']);
            return $this->redirect("/item/$itemId#comments");
        }

        $id = $this->app->comments->create($itemId, (int) $this->user()['id'], $result['data']['body']);
        if (!$request->wantsJson()) {
            return $this->redirect("/item/$itemId#comment-$id");
        }
        $comment = $this->app->comments->find($id);
        return Response::json([
            'ok' => true,
            'comment' => [
                'id' => $id,
                'author_name' => $comment['author_name'],
                'created_at' => $comment['created_at'],
                'created_label' => format_datetime($comment['created_at']),
                'body' => $comment['body'],
                'delete_url' => "/comments/$id/delete",
            ],
        ], 201);
    }

    public function delete(Request $request, array $params): Response
    {
        $comment = $this->app->comments->find((int) $params['id']);
        if ($comment === null) {
            return $this->notFound();
        }
        $user = $this->user();
        if ($comment['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
            return $this->forbidden('Vous ne pouvez supprimer que vos propres commentaires.');
        }
        $this->app->comments->delete((int) $comment['id']);
        $this->flash('success', 'Commentaire supprimé.');
        return $this->redirect('/item/' . (int) $comment['item_id'] . '#comments');
    }
}
