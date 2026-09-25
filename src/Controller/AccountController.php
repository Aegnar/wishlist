<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Validator;

final class AccountController extends Controller
{
    public function show(Request $request, array $params): Response
    {
        return $this->render('account', ['title' => 'Mon compte', 'errors' => []]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->user();
        $new = $this->text($request, 'new_password');
        $errors = [];
        if (!password_verify($this->text($request, 'current_password'), $user['password_hash'])) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        }
        $passwordError = Validator::password($new, $this->text($request, 'new_password_confirm'));
        if ($passwordError !== null) {
            $errors['new_password'] = $passwordError;
        }
        if ($errors !== []) {
            return $this->render('account', ['title' => 'Mon compte', 'errors' => $errors], 422);
        }
        $this->app->users->updatePassword((int) $user['id'], $new);
        $this->flash('success', 'Mot de passe modifié.');
        return $this->redirect('/account');
    }
}
