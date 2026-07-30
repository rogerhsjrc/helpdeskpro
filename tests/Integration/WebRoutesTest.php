<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class WebRoutesTest extends TestCase
{
    /**
     * Restablece los datos de sesión antes de probar cada ruta.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión utilizados por cada ruta.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Comprueba el flujo integrado de la ruta principal hasta la vista.
     */
    public function testHomeRouteRendersApplicationView(): void
    {
        $router = $this->loadRouter();

        $response = $router->dispatch(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('<!DOCTYPE html>', $response->content());
        self::assertStringContainsString('núcleo HTTP', $response->content());
    }

    /**
     * Comprueba el flujo integrado de una ruta inexistente.
     */
    public function testUnknownRouteRendersApplicationNotFoundView(): void
    {
        $router = $this->loadRouter();

        $response = $router->dispatch(new Request('GET', '/ruta-inexistente'));

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString(
            'La página solicitada no existe.',
            $response->content()
        );
    }

    /**
     * Renderiza el formulario de login para un usuario invitado.
     */
    public function testGuestCanViewLoginForm(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/login')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Iniciar sesión', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Protege el dashboard y redirige al invitado.
     */
    public function testDashboardRedirectsGuestToLogin(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/dashboard')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Muestra el dashboard provisional al usuario autenticado.
     */
    public function testAuthenticatedUserCanViewDashboard(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/dashboard')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            'Bienvenido',
            $response->content()
        );
        self::assertMatchesRegularExpression(
            '/Administrador\s+Principal/',
            $response->content()
        );
        self::assertStringContainsString(
            'action="/logout"',
            $response->content()
        );
    }

    /**
     * Evita mostrar nuevamente el login a un usuario autenticado.
     */
    public function testLoginRedirectsAuthenticatedUserToDashboard(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/login')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/dashboard', $response->headers()['Location']);
    }

    /**
     * Rechaza el envío del login cuando falta el token CSRF.
     */
    public function testLoginPostRequiresCsrfToken(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('POST', '/login')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Cierra una sesión autenticada mediante POST y un token CSRF válido.
     */
    public function testLogoutDestroysAuthenticatedSession(): void
    {
        Session::setUser($this->authenticatedUser());
        $csrfToken = Session::csrfToken();

        $response = $this->loadRouter()->dispatch(new Request(
            'POST',
            '/logout',
            [],
            ['_token' => $csrfToken]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertFalse(Session::authenticated());
        self::assertSame([], $_SESSION);
    }

    /**
     * Rechaza el logout autenticado cuando falta el token CSRF.
     */
    public function testLogoutPostRequiresCsrfToken(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('POST', '/logout')
        );

        self::assertSame(403, $response->statusCode());
        self::assertTrue(Session::authenticated());
    }

    /**
     * Impide cerrar sesión mediante una navegación GET.
     */
    public function testLogoutDoesNotAcceptGetRequest(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/logout')
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->headers()['Allow']);
        self::assertTrue(Session::authenticated());
    }

    /**
     * Carga las rutas web reales en una instancia aislada.
     */
    private function loadRouter(): Router
    {
        $router = new Router();

        require dirname(__DIR__, 2) . '/routes/web.php';

        return $router;
    }

    /**
     * Proporciona una identidad válida para los escenarios protegidos.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     rol_id: int,
     *     rol: string
     * }
     */
    private function authenticatedUser(): array
    {
        return [
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ];
    }
}
