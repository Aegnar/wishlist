<?php
declare(strict_types=1);

use App\App;
use App\Controller\AuthController;
use App\Router;

return static function (Router $router, App $app): void {
    $auth = new AuthController($app);
    $router->get('/login', $auth->showLogin(...));
    $router->post('/login', $auth->login(...));
    $router->post('/logout', $auth->logout(...));
};
