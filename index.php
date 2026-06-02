<?php

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────
define('BASE_PATH', __DIR__);
define('APP_START', microtime(true));

require BASE_PATH . '/bootstrap/autoload.php';
require BASE_PATH . '/bootstrap/helpers.php';

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

// ── Variables de entorno ───────────────────────────────────
Env::load(BASE_PATH . '/.env');

$debug = Env::get('APP_DEBUG', false) === true;
$isProd = Env::get('APP_ENV') === 'production';

// ── Errores ────────────────────────────────────────────────
if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/storage/logs/error.log');
}

set_exception_handler(function (\Throwable $e) use ($debug): void {
    error_log('[FATAL] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ($debug) {
        Response::serverError($e->getMessage() . "\n\n" . $e->getTraceAsString());
    }
    Response::serverError();
});

// ── Vistas ─────────────────────────────────────────────────
View::setViewsPath(BASE_PATH . '/app/Views');

// ── Sesión segura ──────────────────────────────────────────
Session::start(secureCookie: $isProd);

// ── Request + Router ───────────────────────────────────────
$req    = new Request(basePath: base_path_prefix());
$router = new Router();

require BASE_PATH . '/bootstrap/routes.php';

$router->dispatch($req);
