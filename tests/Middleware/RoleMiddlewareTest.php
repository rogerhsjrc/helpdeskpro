<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\RoleMiddleware;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    /**
     * Restablece la identidad simulada antes de cada escenario de autorización.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión utilizados por la prueba finalizada.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Permite continuar cuando el usuario posee uno de los roles autorizados.
     */
    public function testAllowsUserWithAuthorizedRole(): void
    {
        Session::setUser($this->userWithRole('Administrador'));
        $middleware = new RoleMiddleware(['Administrador']);

        $response = $middleware->process(
            new Request('GET', '/admin/categorias'),
            static fn (Request $request): Response => Response::html('Categorías')
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Categorías', $response->content());
    }

    /**
     * Rechaza con una vista 403 a un técnico sin permiso administrativo.
     */
    public function testRejectsTechnicianWithoutAuthorizedRole(): void
    {
        Session::setUser($this->userWithRole('Técnico'));
        $middleware = new RoleMiddleware(['Administrador']);

        $response = $middleware->process(
            new Request('GET', '/admin/categorias'),
            static fn (Request $request): Response => Response::html('Categorías')
        );

        self::assertSame(403, $response->statusCode());
        self::assertStringContainsString('<!DOCTYPE html>', $response->content());
        self::assertStringContainsString(
            'No tienes permisos para acceder a esta página.',
            $response->content()
        );
    }

    /**
     * Rechaza con 403 a un cliente sin ejecutar el destino protegido.
     */
    public function testRejectsClientWithoutExecutingProtectedDestination(): void
    {
        Session::setUser($this->userWithRole('Cliente'));
        $destinationWasExecuted = false;
        $middleware = new RoleMiddleware(['Administrador']);

        $response = $middleware->process(
            new Request('GET', '/admin/categorias'),
            static function (Request $request) use (&$destinationWasExecuted): Response {
                $destinationWasExecuted = true;

                return Response::html('Categorías');
            }
        );

        self::assertSame(403, $response->statusCode());
        self::assertFalse($destinationWasExecuted);
    }

    /**
     * Redirige al login cuando el middleware se ejecuta sin identidad válida.
     */
    public function testRedirectsGuestToLogin(): void
    {
        $middleware = new RoleMiddleware(['Administrador']);

        $response = $middleware->process(
            new Request('GET', '/admin/categorias'),
            static fn (Request $request): Response => Response::html('Categorías')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertSame(
            'Debes iniciar sesión para acceder a esa página.',
            Session::pullFlash('error')
        );
    }

    /**
     * Permite configurar más de un rol autorizado para futuros recursos compartidos.
     */
    public function testAllowsAnyConfiguredRole(): void
    {
        Session::setUser($this->userWithRole('Técnico'));
        $middleware = new RoleMiddleware(['Administrador', 'Técnico']);

        $response = $middleware->process(
            new Request('GET', '/tickets'),
            static fn (Request $request): Response => Response::html('Tickets')
        );

        self::assertSame(200, $response->statusCode());
    }

    /**
     * Impide crear un middleware que no declare roles utilizables.
     */
    public function testRejectsEmptyRoleConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'RoleMiddleware necesita al menos un nombre de rol válido.'
        );

        new RoleMiddleware([]);
    }

    /**
     * Construye una identidad válida con el rol requerido por cada escenario.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     rol_id: int,
     *     rol: string
     * }
     */
    private function userWithRole(string $roleName): array
    {
        return [
            'id' => 1,
            'nombre' => 'Usuario',
            'apellido' => 'Autorizado',
            'rol_id' => 1,
            'rol' => $roleName,
        ];
    }
}
