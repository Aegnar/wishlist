<?php
declare(strict_types=1);

use App\App;
use App\Controller\AuthController;
use App\Controller\ItemController;
use App\Controller\MediaController;
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

    $media = new MediaController($app);
    $router->get('/media/{file}', $media->show(...));
};
