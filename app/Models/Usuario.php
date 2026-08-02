<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Usuario
{
    private readonly PDO $databaseConnection;

    /**
     * Recibe una conexión controlada o utiliza la conexión compartida de la aplicación.
     */
    public function __construct(?PDO $databaseConnection = null)
    {
        $this->databaseConnection = $databaseConnection ?? Database::connection();
    }

    /**
     * Busca un usuario activo y su rol mediante una dirección de correo.
     *
     * El estado del rol no interviene en la autenticación.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     email: string,
     *     password: string,
     *     rol_id: int,
     *     rol: string
     * }|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        $findUserStatement = $this->databaseConnection->prepare(
            'SELECT
                usuarios.id,
                usuarios.nombre,
                usuarios.apellido,
                usuarios.email,
                usuarios.password,
                usuarios.rol_id,
                roles.nombre AS rol
             FROM usuarios
             INNER JOIN roles ON roles.id = usuarios.rol_id
             WHERE usuarios.email = :email
               AND usuarios.activo = 1
             LIMIT 1'
        );
        $findUserStatement->execute([
            'email' => $email,
        ]);

        $usuario = $findUserStatement->fetch();

        if (!is_array($usuario)) {
            return null;
        }

        return [
            'id' => (int) $usuario['id'],
            'nombre' => (string) $usuario['nombre'],
            'apellido' => (string) $usuario['apellido'],
            'email' => (string) $usuario['email'],
            'password' => (string) $usuario['password'],
            'rol_id' => (int) $usuario['rol_id'],
            'rol' => (string) $usuario['rol'],
        ];
    }

    /**
     * Registra en la base de datos el último acceso exitoso del usuario.
     */
    public function updateLastAccess(int $usuarioId): void
    {
        $updateLastAccessStatement = $this->databaseConnection->prepare(
            'UPDATE usuarios
             SET ultimo_acceso_at = NOW()
             WHERE id = :usuario_id'
        );
        $updateLastAccessStatement->execute([
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Lista técnicos activos disponibles para asignar tickets.
     *
     * @return list<array{id: int, nombre: string, apellido: string, email: string}>
     */
    public function activeTechnicians(): array
    {
        $listTechniciansStatement = $this->databaseConnection->prepare(
            'SELECT usuarios.id, usuarios.nombre, usuarios.apellido, usuarios.email
             FROM usuarios
             INNER JOIN roles ON roles.id = usuarios.rol_id
             WHERE usuarios.activo = 1
               AND roles.nombre = :rol
             ORDER BY usuarios.apellido ASC, usuarios.nombre ASC'
        );
        $listTechniciansStatement->execute(['rol' => 'Técnico']);
        $technicianRows = $listTechniciansStatement->fetchAll();

        return array_map(
            static fn (array $technicianRow): array => [
                'id' => (int) $technicianRow['id'],
                'nombre' => (string) $technicianRow['nombre'],
                'apellido' => (string) $technicianRow['apellido'],
                'email' => (string) $technicianRow['email'],
            ],
            $technicianRows
        );
    }

    /**
     * Busca un técnico activo por identificador y confirma su rol actual.
     *
     * @return array{id: int, nombre: string, apellido: string, email: string}|null
     */
    public function findActiveTechnicianById(int $technicianId): ?array
    {
        $findTechnicianStatement = $this->databaseConnection->prepare(
            'SELECT usuarios.id, usuarios.nombre, usuarios.apellido, usuarios.email
             FROM usuarios
             INNER JOIN roles ON roles.id = usuarios.rol_id
             WHERE usuarios.id = :tecnico_id
               AND usuarios.activo = 1
               AND roles.nombre = :rol
             LIMIT 1'
        );
        $findTechnicianStatement->execute([
            'tecnico_id' => $technicianId,
            'rol' => 'Técnico',
        ]);
        $technicianRow = $findTechnicianStatement->fetch();

        if (!is_array($technicianRow)) {
            return null;
        }

        return [
            'id' => (int) $technicianRow['id'],
            'nombre' => (string) $technicianRow['nombre'],
            'apellido' => (string) $technicianRow['apellido'],
            'email' => (string) $technicianRow['email'],
        ];
    }
}
