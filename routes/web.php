<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CategoriaController;
use App\Controllers\ConfiguracionController;
use App\Controllers\DashboardController;
use App\Controllers\ErrorController;
use App\Controllers\EstadoTicketController;
use App\Controllers\HomeController;
use App\Controllers\PrioridadController;
use App\Controllers\TicketController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RoleMiddleware;

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
$ticketReaderMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Administrador', 'Técnico', 'Cliente']),
];
$router->get(
    '/tickets',
    [TicketController::class, 'index'],
    $ticketReaderMiddleware
);
$clientTicketCreationMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Cliente']),
];
$router->get(
    '/tickets/crear',
    [TicketController::class, 'create'],
    $clientTicketCreationMiddleware
);
$router->post(
    '/tickets',
    [TicketController::class, 'store'],
    [...$clientTicketCreationMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/tickets/{codigo}',
    [TicketController::class, 'show'],
    $ticketReaderMiddleware
);
$ticketEditorMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Administrador', 'Cliente']),
];
$router->get(
    '/tickets/{codigo}/editar',
    [TicketController::class, 'edit'],
    $ticketEditorMiddleware
);
$router->post(
    '/tickets/{codigo}/actualizar',
    [TicketController::class, 'update'],
    [...$ticketEditorMiddleware, new CsrfMiddleware()]
);
$ticketAssignmentMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Administrador']),
];
$router->get(
    '/tickets/{codigo}/asignar',
    [TicketController::class, 'assignment'],
    $ticketAssignmentMiddleware
);
$router->post(
    '/tickets/{codigo}/asignacion',
    [TicketController::class, 'updateAssignment'],
    [...$ticketAssignmentMiddleware, new CsrfMiddleware()]
);
$ticketWorkflowMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Administrador', 'Técnico']),
];
$router->get(
    '/tickets/{codigo}/gestionar',
    [TicketController::class, 'workflow'],
    $ticketWorkflowMiddleware
);
$router->post(
    '/tickets/{codigo}/flujo',
    [TicketController::class, 'updateWorkflow'],
    [...$ticketWorkflowMiddleware, new CsrfMiddleware()]
);
$administratorMiddleware = [
    new AuthMiddleware(),
    new RoleMiddleware(['Administrador']),
];
$router->get(
    '/admin/configuraciones',
    [ConfiguracionController::class, 'index'],
    $administratorMiddleware
);
$router->get(
    '/admin/categorias',
    [CategoriaController::class, 'index'],
    $administratorMiddleware
);
$router->get(
    '/admin/categorias/crear',
    [CategoriaController::class, 'create'],
    $administratorMiddleware
);
$router->post(
    '/admin/categorias',
    [CategoriaController::class, 'store'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/admin/categorias/{id}/editar',
    [CategoriaController::class, 'edit'],
    $administratorMiddleware
);
$router->post(
    '/admin/categorias/{id}/actualizar',
    [CategoriaController::class, 'update'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->post(
    '/admin/categorias/{id}/estado',
    [CategoriaController::class, 'updateStatus'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/admin/prioridades',
    [PrioridadController::class, 'index'],
    $administratorMiddleware
);
$router->get(
    '/admin/prioridades/crear',
    [PrioridadController::class, 'create'],
    $administratorMiddleware
);
$router->post(
    '/admin/prioridades',
    [PrioridadController::class, 'store'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/admin/prioridades/{id}/editar',
    [PrioridadController::class, 'edit'],
    $administratorMiddleware
);
$router->post(
    '/admin/prioridades/{id}/actualizar',
    [PrioridadController::class, 'update'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->post(
    '/admin/prioridades/{id}/estado',
    [PrioridadController::class, 'updateStatus'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/admin/estados-ticket',
    [EstadoTicketController::class, 'index'],
    $administratorMiddleware
);
$router->get(
    '/admin/estados-ticket/crear',
    [EstadoTicketController::class, 'create'],
    $administratorMiddleware
);
$router->post(
    '/admin/estados-ticket',
    [EstadoTicketController::class, 'store'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->get(
    '/admin/estados-ticket/{id}/editar',
    [EstadoTicketController::class, 'edit'],
    $administratorMiddleware
);
$router->post(
    '/admin/estados-ticket/{id}/actualizar',
    [EstadoTicketController::class, 'update'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->post(
    '/admin/estados-ticket/{id}/estado',
    [EstadoTicketController::class, 'updateStatus'],
    [...$administratorMiddleware, new CsrfMiddleware()]
);
$router->setNotFoundHandler([ErrorController::class, 'notFound']);
