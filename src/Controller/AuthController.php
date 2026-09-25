<?php
declare(strict_types=1);

namespace App\Controller;

use App\Auth;
use App\Http\Request;
use App\Http\Response;

final class AuthController extends Controller
{
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->app->auth->user() !== null) {
            return $this->redirect('/');
        }
        return $this->render('login', ['title' => 'Connexion', 'username' => '', 'error' => null]);
    }

    public function login(Request $request, array $params): Response
    {
        $username = trim($this->text($request, 'username'));
        $password = $this->text($request, 'password');
        $auth = $this->app->auth;

        if ($auth->isRateLimited($request->ip(), $username)) {
            return $this->render('login', [
                'title' => 'Connexion',
                'username' => $username,
                'error' => 'Trop de tentatives. Réessayez dans ' . Auth::WINDOW_MINUTES . ' minutes.',
            ], 429);
        }
        $user = $auth->attempt($username, $password, $request->ip());
        if ($user === null) {
            return $this->render('login', ['title' => 'Connexion', 'username' => $username, 'error' => 'Identifiants invalides.'], 422);
        }
        $auth->login($user, $request->input('remember') === '1');
        return $this->redirect('/');
    }

    public function logout(Request $request, array $params): Response
    {
        $this->app->auth->logout();
        return $this->redirect('/login');
    }
}
