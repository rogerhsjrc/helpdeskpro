<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\GuestMiddleware;
use PHPUnit\Framework\TestCase;

final class GuestMiddlewareTest extends TestCase
{
    /**
     * Restablece la sesión simulada antes de cada escenario.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina la sesión simulada al finalizar cada escenario.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Permite al invitado consultar una ruta de autenticación.
     */
    public function testAllowsGuestUser(): void
    {
        $middleware = new GuestMiddleware();

        $response = $middleware->process(
            new Request('GET', '/login'),
            static fn (Request $request): Response => Response::html('Login')
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Login', $response->content());
    }

    /**
     * Redirige al dashboard cuando el usuario ya inició sesión.
     */
    public function testRedirectsAuthenticatedUserToDashboard(): void
    {
        Session::setUser([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        $middleware = new GuestMiddleware();

        $response = $middleware->process(
            new Request('GET', '/login'),
            static fn (Request $request): Response => Response::html('Login')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/dashboard', $response->headers()['Location']);
    }
}
