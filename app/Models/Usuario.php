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
}
