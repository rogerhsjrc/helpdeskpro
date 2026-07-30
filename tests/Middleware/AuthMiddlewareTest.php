<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
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
     * Redirige al invitado y deja un mensaje temporal explicativo.
     */
    public function testRedirectsGuestToLogin(): void
    {
        $middleware = new AuthMiddleware();

        $response = $middleware->process(
            new Request('GET', '/dashboard'),
            static fn (Request $request): Response => Response::html('Privada')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertSame(
            'Debes iniciar sesión para acceder a esa página.',
            Session::pullFlash('error')
        );
    }

    /**
     * Permite que un usuario autenticado alcance el controlador protegido.
     */
    public function testAllowsAuthenticatedUser(): void
    {
        Session::setUser([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        $middleware = new AuthMiddleware();

        $response = $middleware->process(
            new Request('GET', '/dashboard'),
            static fn (Request $request): Response => Response::html('Privada')
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Privada', $response->content());
    }
}
