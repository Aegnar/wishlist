<?php
declare(strict_types=1);

use App\App;
use App\Controller\AuthController;
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

    $tags = new TagController($app);
    $router->get('/tags/suggest', $tags->suggest(...));

    $media = new MediaController($app);
    $router->get('/media/{file}', $media->show(...));
};
