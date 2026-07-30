<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\AuthController;
use App\Core\Request;
use App\Core\Session;
use App\Models\Usuario;
use App\Services\AuthService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    /**
     * Restablece la sesión simulada antes de cada escenario.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión creados por cada escenario.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Renderiza errores y correos previos escapando el contenido dinámico.
     */
    public function testShowsLoginFormWithFlashMessages(): void
    {
        Session::flash('error', 'Credenciales <inválidas>.');
        Session::flash('email', '"><script>alert(1)</script>');
        $controller = new AuthController();

        $response = $controller->showLogin(new Request('GET', '/login'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            'Credenciales &lt;inválidas&gt;.',
            $response->content()
        );
        self::assertStringNotContainsString(
            '<script>alert(1)</script>',
            $response->content()
        );
        self::assertMatchesRegularExpression(
            '/name="_token"[\s\S]+value="[a-f0-9]{64}"/',
            $response->content()
        );
    }

    /**
     * Conserva el correo y muestra un mensaje genérico ante credenciales inválidas.
     */
    public function testRejectsInvalidCredentialsWithGenericMessage(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($findUserStatement);
        $findUserStatement->method('fetch')->willReturn(false);
        $controller = new AuthController(
            new AuthService(new Usuario($databaseConnection))
        );

        $response = $controller->login(new Request(
            'POST',
            '/login',
            [],
            [
                'email' => ' usuario@helpdesk.local ',
                'password' => 'incorrecta',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertSame(
            'Las credenciales ingresadas no son válidas.',
            Session::pullFlash('error')
        );
        self::assertSame(
            'usuario@helpdesk.local',
            Session::pullFlash('email')
        );
        self::assertFalse(Session::authenticated());
    }

    /**
     * Regenera la sesión, guarda la identidad y renueva CSRF tras un login válido.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCreatesSecureSessionWithValidCredentials(): void
    {
        session_save_path(dirname(__DIR__, 2) . '/storage/sessions');
        session_id('authcontrollerbefore');
        session_start();
        $previousSessionId = session_id();
        $previousCsrfToken = Session::csrfToken();
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createStub(PDOStatement::class);
        $updateLastAccessStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findUserStatement,
                $updateLastAccessStatement
            );
        $findUserStatement->method('fetch')->willReturn([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'email' => 'admin@helpdesk.local',
            'password' => password_hash('Password-seguro-123', PASSWORD_DEFAULT),
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        $updateLastAccessStatement->expects(self::once())
            ->method('execute')
            ->with(['usuario_id' => 1])
            ->willReturn(true);
        $controller = new AuthController(
            new AuthService(new Usuario($databaseConnection))
        );

        $response = $controller->login(new Request(
            'POST',
            '/login',
            [],
            [
                'email' => 'admin@helpdesk.local',
                'password' => 'Password-seguro-123',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/dashboard', $response->headers()['Location']);
        self::assertNotSame($previousSessionId, session_id());
        self::assertNotSame($previousCsrfToken, Session::csrfToken());
        self::assertSame(1, Session::user()['id']);

        Session::destroy();
    }

    /**
     * Destruye los datos y finaliza por completo una sesión activa.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutDestroysActiveSession(): void
    {
        session_save_path(dirname(__DIR__, 2) . '/storage/sessions');
        session_id('authcontrollerlogout');
        session_start();
        Session::setUser([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        Session::csrfToken();
        $controller = new AuthController();

        $response = $controller->logout(new Request('POST', '/logout'));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }
}
