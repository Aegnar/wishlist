<?php
declare(strict_types=1);

use App\App;
use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Kernel;
use App\Logger;
use App\Router;
use App\Session;
use App\View;

require dirname(__DIR__) . '/src/autoload.php';

$root = dirname(__DIR__);
$view = new View($root . '/templates');
$debug = false;
$logger = null;

try {
    $config = Config::load();
    $debug = (bool) $config['app']['debug'];
    $logger = new Logger($config['paths']['storage'] . '/logs/app.log');
    $app = new App($config, $root);

    if (!$app->migrator->isUpToDate()) {
        $response = Response::html($view->render('errors/fatal', [
            'heading' => 'Maintenance',
            'message' => 'La base de données doit être mise à jour : lancer « php bin/db.php upgrade » sur le serveur.',
        ], null), 503);
    } else {
        Session::start($config['app'], $config['paths']['storage'] . '/sessions');
        $router = new Router();
        (require $root . '/src/routes.php')($router, $app);
        $response = (new Kernel($app, $router))->handle(Request::fromGlobals());
    }
} catch (Throwable $e) {
    if ($logger !== null) {
        $logger->error($e, ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'user' => $_SESSION['user_id'] ?? null]);
    } else {
        error_log((string) $e);
    }
    $response = Response::html($view->render('errors/fatal', [
        'heading' => 'Erreur',
        'message' => 'Une erreur est survenue. Réessayez dans un instant.',
        'detail' => $debug ? (string) $e : null,
    ], null), 500);
}

Kernel::secure($response)->send();
