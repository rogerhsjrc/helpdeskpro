<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);
Env::load($rootPath . '/.env');

$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$sessionPath = $rootPath . '/storage/sessions';

session_name('helpdesk_pro_session');
session_save_path($sessionPath);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isProduction && $isHttps,
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

try {
    $request = Request::capture();
    $router = new Router();

    require $rootPath . '/routes/web.php'; // Hace las veces de Routes.php en un framework más grande

    $response = $router->dispatch($request);
} catch (Throwable $exception) {
    error_log($exception->__toString());

    $debugEnabled = filter_var(
        $_ENV['APP_DEBUG'] ?? false,
        FILTER_VALIDATE_BOOL
    );
    $content = $debugEnabled
        ? '<h1>Error interno</h1><pre>'
            . htmlspecialchars($exception->__toString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre>'
        : '<h1>Error interno</h1><p>No se pudo procesar la solicitud.</p>';

    $response = Response::html($content, 500);
}

$response->send();
