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
        self::assertStringContainsString(
            'href="/admin/configuraciones"',
            $response->content()
        );
    }

    /**
     * Oculta la navegación administrativa del dashboard de un técnico.
     */
    public function testTechnicianDashboardDoesNotShowAdministrativeNavigation(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 2));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/dashboard')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString(
            'href="/admin/configuraciones"',
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
     * Ejecuta el destino administrativo cuando el pipeline recibe un administrador.
     */
    public function testAdministrativePipelineAllowsAdministrator(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/configuraciones')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('<h1>Configuraciones</h1>', $response->content());
        self::assertStringContainsString('href="/admin/categorias"', $response->content());
        self::assertStringContainsString('href="/admin/prioridades"', $response->content());
        self::assertStringContainsString('href="/admin/estados-ticket"', $response->content());
    }

    /**
     * Detiene el pipeline administrativo con 403 para un técnico autenticado.
     */
    public function testAdministrativePipelineRejectsTechnician(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 2));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/configuraciones')
        );

        self::assertSame(403, $response->statusCode());
        self::assertStringContainsString('Acceso denegado', $response->content());
    }

    /**
     * Detiene el pipeline administrativo con 403 para un cliente autenticado.
     */
    public function testAdministrativePipelineRejectsClient(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 3));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/configuraciones')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Redirige al invitado antes de evaluar el rol de la ruta administrativa.
     */
    public function testAdministrativePipelineRedirectsGuest(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/configuraciones')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Permite al administrador consultar el formulario real de nueva categoría.
     */
    public function testAdministratorCanViewCreateCategoryRoute(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/categorias/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nueva categoría', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Impide que un técnico consulte el formulario administrativo de categorías.
     */
    public function testTechnicianCannotViewCreateCategoryRoute(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 2));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/categorias/crear')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Redirige al invitado que intenta consultar el listado de categorías.
     */
    public function testGuestCannotViewCategoryListRoute(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/categorias')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Rechaza el alta administrativa cuando falta el token CSRF.
     */
    public function testCreateCategoryRouteRequiresCsrfToken(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request(
                'POST',
                '/admin/categorias',
                [],
                [
                    'nombre' => 'Impresoras',
                    'descripcion' => '',
                ]
            )
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Rechaza cambios de estado por GET sin ejecutar una mutación.
     */
    public function testCategoryStatusRouteDoesNotAcceptGetRequest(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/categorias/1/estado')
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    /**
     * Permite al administrador consultar el formulario real de prioridades.
     */
    public function testAdministratorCanViewCreatePriorityRoute(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/prioridades/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nueva prioridad', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Impide que un cliente consulte el formulario administrativo de prioridades.
     */
    public function testClientCannotViewCreatePriorityRoute(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 3));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/prioridades/crear')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Redirige al invitado que intenta consultar las prioridades.
     */
    public function testGuestCannotViewPriorityListRoute(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/prioridades')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Rechaza el alta de prioridad cuando falta el token CSRF.
     */
    public function testCreatePriorityRouteRequiresCsrfToken(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request(
                'POST',
                '/admin/prioridades',
                [],
                [
                    'nombre' => 'Crítica',
                    'nivel' => '5',
                    'descripcion' => '',
                    'color' => '#ff0000',
                ]
            )
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Rechaza cambios de estado de prioridad realizados mediante GET.
     */
    public function testPriorityStatusRouteDoesNotAcceptGetRequest(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/prioridades/1/estado')
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    /**
     * Permite al administrador consultar el formulario real de estados.
     */
    public function testAdministratorCanViewCreateTicketStatusRoute(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/estados-ticket/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nuevo estado de ticket', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Impide que un técnico consulte el formulario administrativo de estados.
     */
    public function testTechnicianCannotViewCreateTicketStatusRoute(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 2));

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/estados-ticket/crear')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Redirige al invitado que intenta consultar los estados.
     */
    public function testGuestCannotViewTicketStatusListRoute(): void
    {
        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/estados-ticket')
        );

        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Rechaza el alta de estado cuando falta el token CSRF.
     */
    public function testCreateTicketStatusRouteRequiresCsrfToken(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request(
                'POST',
                '/admin/estados-ticket',
                [],
                [
                    'nombre' => 'Archivado',
                    'descripcion' => '',
                    'orden' => '8',
                    'es_final' => '1',
                ]
            )
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Rechaza cambios de estado lógico realizados mediante GET.
     */
    public function testTicketStatusActivationRouteDoesNotAcceptGetRequest(): void
    {
        Session::setUser($this->authenticatedUser());

        $response = $this->loadRouter()->dispatch(
            new Request('GET', '/admin/estados-ticket/1/estado')
        );

        self::assertSame(405, $response->statusCode());
        self::assertSame('POST', $response->headers()['Allow']);
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
    private function authenticatedUser(
        string $roleName = 'Administrador',
        int $roleId = 1
    ): array
    {
        return [
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => $roleId,
            'rol' => $roleName,
        ];
    }
}
