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

// Erreurs fatales (mémoire épuisée, délai dépassé…) : non capturables par le try/catch ci-dessous,
// on les consigne dans le journal de l'application dès qu'il est disponible.
register_shutdown_function(static function () use (&$logger): void {
    $error = error_get_last();
    if ($error === null || !($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
        return;
    }
    if ($logger instanceof Logger) {
        $logger->error(
            new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']),
            ['uri' => $_SERVER['REQUEST_URI'] ?? '', 'fatal' => true],
        );
    }
});

try {
    $config = Config::load();
    $debug = (bool) $config['app']['debug'];
    $logger = new Logger($config['paths']['storage'] . '/logs/app.log');
    $app = new App($config, $root);

    if (!$app->migrator->isUpToDate()) {
        $command = $app->migrator->tableExists() ? 'upgrade' : 'install';
        $response = Response::html($view->render('errors/fatal', [
            'heading' => 'Maintenance',
            'message' => "La base de données doit être mise à jour : lancer « php bin/db.php $command » sur le serveur.",
        ], null), 503);
    } else {
        $request = Request::fromGlobals();
        if (Session::shouldStart($_COOKIE, $request->path(), (string) $config['app']['session_name'])) {
            Session::start($config['app'], $config['paths']['storage'] . '/sessions');
        } else {
            // Visiteur sans session : rien n'est écrit sur disque (il sera redirigé vers /login).
            $_SESSION = [];
        }
        $router = new Router();
        (require $root . '/src/routes.php')($router, $app);
        $response = (new Kernel($app, $router))->handle($request);
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
