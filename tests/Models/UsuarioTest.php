<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Usuario;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class UsuarioTest extends TestCase
{
    /**
     * Lista únicamente usuarios activos cuyo rol actual es Técnico.
     */
    public function testListsActiveTechnicians(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $statement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains($query, 'usuarios.activo = 1')
                    && str_contains($query, 'roles.nombre = :rol')
            ))
            ->willReturn($statement);
        $statement->method('fetchAll')->willReturn([[
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]]);
        $usuarioModel = new Usuario($databaseConnection);

        $technicians = $usuarioModel->activeTechnicians();

        self::assertSame(4, $technicians[0]['id']);
        self::assertSame('Ana', $technicians[0]['nombre']);
    }

    /**
     * Confirma por identificador que el candidato continúa activo y es técnico.
     */
    public function testFindsActiveTechnicianById(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with([
            'tecnico_id' => 4,
            'rol' => 'Técnico',
        ])->willReturn(true);
        $statement->method('fetch')->willReturn([
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]);
        $usuarioModel = new Usuario($databaseConnection);

        $technician = $usuarioModel->findActiveTechnicianById(4);

        self::assertSame('ana@example.test', $technician['email']);
    }

    /**
     * Comprueba la búsqueda preparada y el filtro exclusivo del usuario activo.
     */
    public function testFindsActiveUserByEmailWithoutFilteringRoleStatus(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findUserStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static function (string $query): bool {
                    return str_contains($query, 'usuarios.activo = 1')
                        && str_contains($query, 'INNER JOIN roles')
                        && !str_contains($query, 'roles.activo');
                }
            ))
            ->willReturn($findUserStatement);
        $findUserStatement->expects(self::once())
            ->method('execute')
            ->with(['email' => 'admin@helpdesk.local'])
            ->willReturn(true);
        $findUserStatement->expects(self::once())
            ->method('fetch')
            ->willReturn([
                'id' => 1,
                'nombre' => 'Administrador',
                'apellido' => 'Principal',
                'email' => 'admin@helpdesk.local',
                'password' => 'hash-seguro',
                'rol_id' => 1,
                'rol' => 'Administrador',
            ]);
        $usuarioModel = new Usuario($databaseConnection);

        $usuario = $usuarioModel->findActiveByEmail('admin@helpdesk.local');

        self::assertSame(1, $usuario['id']);
        self::assertSame('Administrador', $usuario['rol']);
        self::assertSame('hash-seguro', $usuario['password']);
    }

    /**
     * Devuelve null cuando no existe un usuario activo con el correo indicado.
     */
    public function testReturnsNullWhenActiveUserDoesNotExist(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findUserStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findUserStatement);
        $findUserStatement->expects(self::once())
            ->method('execute')
            ->with(['email' => 'inactivo@helpdesk.local'])
            ->willReturn(true);
        $findUserStatement->method('fetch')->willReturn(false);
        $usuarioModel = new Usuario($databaseConnection);

        self::assertNull(
            $usuarioModel->findActiveByEmail('inactivo@helpdesk.local')
        );
    }

    /**
     * Actualiza el último acceso utilizando el identificador como parámetro.
     */
    public function testUpdatesLastAccessWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updateLastAccessStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'SET ultimo_acceso_at = NOW()'
                )
            ))
            ->willReturn($updateLastAccessStatement);
        $updateLastAccessStatement->expects(self::once())
            ->method('execute')
            ->with(['usuario_id' => 7])
            ->willReturn(true);
        $usuarioModel = new Usuario($databaseConnection);

        $usuarioModel->updateLastAccess(7);
    }
}
