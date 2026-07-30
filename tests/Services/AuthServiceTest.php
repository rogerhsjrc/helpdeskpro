<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\Usuario;
use App\Services\AuthService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    /**
     * Autentica credenciales válidas, normaliza el correo y registra el acceso.
     */
    public function testAuthenticatesActiveUserAndUpdatesLastAccess(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createMock(PDOStatement::class);
        $updateLastAccessStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findUserStatement,
                $updateLastAccessStatement
            );
        $findUserStatement->expects(self::once())
            ->method('execute')
            ->with(['email' => 'admin@helpdesk.local'])
            ->willReturn(true);
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
        $authService = new AuthService(new Usuario($databaseConnection));

        $authenticatedUser = $authService->authenticate(
            '  ADMIN@HELPDESK.LOCAL ',
            'Password-seguro-123'
        );

        self::assertSame([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ], $authenticatedUser);
    }

    /**
     * Rechaza una contraseña incorrecta sin actualizar el último acceso.
     */
    public function testRejectsInvalidPasswordWithoutUpdatingLastAccess(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($findUserStatement);
        $findUserStatement->method('fetch')->willReturn([
            'id' => 1,
            'nombre' => 'Administrador',
            'apellido' => 'Principal',
            'email' => 'admin@helpdesk.local',
            'password' => password_hash('Password-seguro-123', PASSWORD_DEFAULT),
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        $authService = new AuthService(new Usuario($databaseConnection));

        $authenticatedUser = $authService->authenticate(
            'admin@helpdesk.local',
            'Password-incorrecto'
        );

        self::assertNull($authenticatedUser);
    }

    /**
     * Rechaza un usuario inexistente o inactivo sin ejecutar una actualización.
     */
    public function testRejectsUnknownOrInactiveUser(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($findUserStatement);
        $findUserStatement->method('fetch')->willReturn(false);
        $authService = new AuthService(new Usuario($databaseConnection));

        self::assertNull(
            $authService->authenticate(
                'inactivo@helpdesk.local',
                'Password-seguro-123'
            )
        );
    }

    /**
     * Rechaza entradas mal formadas antes de consultar la base de datos.
     */
    public function testRejectsInvalidInputWithoutQueryingUser(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $authService = new AuthService(new Usuario($databaseConnection));

        self::assertNull(
            $authService->authenticate('correo-invalido', 'contraseña')
        );
        self::assertNull(
            $authService->authenticate('admin@helpdesk.local', '')
        );
    }
}
