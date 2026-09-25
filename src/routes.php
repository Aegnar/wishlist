<?php
declare(strict_types=1);

use App\App;
use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\CommentController;
use App\Controller\ItemController;
use App\Controller\MediaController;
use App\Controller\TagController;
use App\Router;

return static function (Router $router, App $app): void {
    $auth = new AuthController($app);
    $router->get('/login', $auth->showLogin(...));
    $router->post('/login', $auth->login(...));
    $router->post('/logout', $auth->logout(...));

    $items = new ItemController($app);
    $router->get('/', $items->index(...));
    $router->get('/purchased', $items->purchased(...));
    $router->get('/item/{id}', $items->show(...));
    $router->get('/item/new', $items->create(...));
    $router->post('/item', $items->store(...));
    $router->get('/item/{id}/edit', $items->edit(...));
    $router->post('/item/{id}', $items->update(...));
    $router->post('/item/{id}/purchase', $items->purchase(...));
    $router->post('/item/{id}/unpurchase', $items->unpurchase(...));
    $router->post('/item/{id}/delete', $items->delete(...));

    $tags = new TagController($app);
    $router->get('/tags/suggest', $tags->suggest(...));

    $media = new MediaController($app);
    $router->get('/media/{file}', $media->show(...));

    $comments = new CommentController($app);
    $router->post('/item/{id}/comments', $comments->store(...));
    $router->post('/comments/{id}/delete', $comments->delete(...));

    $account = new AccountController($app);
    $router->get('/account', $account->show(...));
    $router->post('/account', $account->update(...));

    $admin = new AdminController($app);
    $router->get('/admin', $admin->index(...));
    $router->post('/admin/users', $admin->createUser(...));
    $router->post('/admin/users/{id}/password', $admin->resetPassword(...));
    $router->post('/admin/users/{id}/toggle', $admin->toggleUser(...));
    $router->post('/admin/tags/{id}/rename', $admin->renameTag(...));
    $router->post('/admin/tags/{id}/merge', $admin->mergeTag(...));
    $router->post('/admin/tags/{id}/delete', $admin->deleteTag(...));
};
