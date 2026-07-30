<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ErrorController;
use App\Controllers\HomeController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

if (!isset($router) || !$router instanceof Router) {
    throw new RuntimeException('El archivo de rutas necesita una instancia de Router.');
}

$router->get('/', [HomeController::class, 'index']);
$router->get(
    '/login',
    [AuthController::class, 'showLogin'],
    [new GuestMiddleware()]
);
$router->post(
    '/login',
    [AuthController::class, 'login'],
    [new GuestMiddleware(), new CsrfMiddleware()]
);
$router->get(
    '/dashboard',
    [DashboardController::class, 'index'],
    [new AuthMiddleware()]
);
$router->post(
    '/logout',
    [AuthController::class, 'logout'],
    [new AuthMiddleware(), new CsrfMiddleware()]
);
$router->setNotFoundHandler([ErrorController::class, 'notFound']);
