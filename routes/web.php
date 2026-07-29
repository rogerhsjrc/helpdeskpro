<?php

declare(strict_types=1);

use App\Controllers\ErrorController;
use App\Controllers\HomeController;
use App\Core\Router;

if (!isset($router) || !$router instanceof Router) {
    throw new RuntimeException('El archivo de rutas necesita una instancia de Router.');
}

$router->get('/', [HomeController::class, 'index']);
$router->setNotFoundHandler([ErrorController::class, 'notFound']);
