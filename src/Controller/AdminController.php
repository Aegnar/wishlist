<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Validator;
use InvalidArgumentException;

final class AdminController extends Controller
{
    private const EMPTY_USER = ['username' => '', 'display_name' => '', 'role' => 'user'];

    public function index(Request $request, array $params): Response
    {
        return $this->renderIndex(self::EMPTY_USER, []);
    }

    public function createUser(Request $request, array $params): Response
    {
        $values = [
            'username' => strtolower(trim($this->text($request, 'username'))),
            'display_name' => trim($this->text($request, 'display_name')),
            'role' => $this->text($request, 'role') === 'admin' ? 'admin' : 'user',
        ];
        $password = $this->text($request, 'password');
        $errors = array_filter([
            'username' => Validator::username($values['username']),
            'display_name' => Validator::displayName($values['display_name']),
            'password' => Validator::password($password, $this->text($request, 'password_confirm')),
        ]);
        if (!isset($errors['username']) && $this->app->users->findByUsername($values['username']) !== null) {
            $errors['username'] = 'Cet identifiant est déjà utilisé.';
        }
        if ($errors !== []) {
            return $this->renderIndex($values, $errors, 422);
        }
        $this->app->users->create($values['username'], $values['display_name'], $password, $values['role']);
        $this->flash('success', "Compte « {$values['username']} » créé.");
        return $this->redirect('/admin');
    }

    public function resetPassword(Request $request, array $params): Response
    {
        $target = $this->app->users->find((int) $params['id']);
        if ($target === null) {
            return $this->notFound();
        }
        $password = $this->text($request, 'password');
        $error = Validator::password($password, $this->text($request, 'password_confirm'));
        if ($error !== null) {
            $this->flash('error', "{$target['display_name']} : $error");
        } else {
            $this->app->users->updatePassword((int) $target['id'], $password);
            // Les sessions de l'utilisateur ciblé expirent d'elles-mêmes (empreinte du mot de passe) ;
            // si l'admin réinitialise son propre mot de passe, son appareil courant reste connecté.
            $this->app->auth->refreshAfterPasswordChange((int) $target['id']);
            $this->flash('success', "Mot de passe de {$target['display_name']} réinitialisé.");
        }
        return $this->redirect('/admin#users');
    }

    public function toggleUser(Request $request, array $params): Response
    {
        $target = $this->app->users->find((int) $params['id']);
        if ($target === null) {
            return $this->notFound();
        }
        if ((int) $target['id'] === (int) $this->user()['id']) {
            $this->flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirect('/admin#users');
        }
        $activate = (int) $target['is_active'] !== 1;
        $this->app->users->setActive((int) $target['id'], $activate);
        $this->flash('success', ($activate ? 'Compte réactivé : ' : 'Compte désactivé : ') . $target['display_name']);
        return $this->redirect('/admin#users');
    }

    public function renameTag(Request $request, array $params): Response
    {
        return $this->tagAction((int) $params['id'], function () use ($request, $params): string {
            $this->app->tags->rename((int) $params['id'], $this->text($request, 'name'));
            return 'Tag renommé.';
        });
    }

    public function mergeTag(Request $request, array $params): Response
    {
        return $this->tagAction((int) $params['id'], function () use ($request, $params): string {
            $this->app->tags->merge((int) $params['id'], (int) $this->text($request, 'into'));
            return 'Tags fusionnés.';
        });
    }

    public function deleteTag(Request $request, array $params): Response
    {
        return $this->tagAction((int) $params['id'], function () use ($params): string {
            if (!$this->app->tags->deleteIfUnused((int) $params['id'])) {
                throw new InvalidArgumentException('Ce tag est encore utilisé : fusionnez-le plutôt.');
            }
            return 'Tag supprimé.';
        });
    }

    /** @param callable(): string $action retourne le message de succès, lève InvalidArgumentException sinon */
    private function tagAction(int $id, callable $action): Response
    {
        try {
            if ($this->app->tags->find($id) === null) {
                throw new InvalidArgumentException('Tag introuvable.');
            }
            $this->flash('success', $action());
        } catch (InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin#tags');
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function renderIndex(array $values, array $errors, int $status = 200): Response
    {
        return $this->render('admin/index', [
            'title' => 'Administration',
            'users' => $this->app->users->all(),
            'tags' => $this->app->tags->allWithCounts(),
            'values' => $values,
            'errors' => $errors,
        ], $status);
    }
}
