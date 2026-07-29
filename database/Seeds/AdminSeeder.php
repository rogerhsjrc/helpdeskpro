<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use PDO;
use RuntimeException;

final class AdminSeeder
{
    /**
     * Crea el administrador inicial utilizando credenciales del entorno.
     *
     * @throws RuntimeException Si falta el rol o la contraseña no es segura.
     */
    public function run(): void
    {
        $databaseConnection = Database::connection();
        $administratorEmail = trim(
            (string) ($_ENV['ADMIN_EMAIL'] ?? 'admin@helpdesk.local')
        );

        if ($this->userExists($databaseConnection, $administratorEmail)) {
            echo "El usuario administrador ya existe.\n";

            return;
        }

        $administratorPassword = (string) ($_ENV['ADMIN_PASSWORD'] ?? '');

        if (strlen($administratorPassword) < 12) {
            throw new RuntimeException(
                'ADMIN_PASSWORD debe contener al menos 12 caracteres.'
            );
        }

        $administratorRoleId = $this->findAdministratorRoleId($databaseConnection);
        $insertAdministratorStatement = $databaseConnection->prepare(
            'INSERT INTO usuarios (
                rol_id,
                nombre,
                apellido,
                email,
                password,
                email_verificado_at,
                activo
            ) VALUES (
                :rol_id,
                :nombre,
                :apellido,
                :email,
                :password,
                NOW(),
                1
            )'
        );

        $insertAdministratorStatement->execute([
            'rol_id' => $administratorRoleId,
            'nombre' => $_ENV['ADMIN_NAME'] ?? 'Administrador',
            'apellido' => $_ENV['ADMIN_LAST_NAME'] ?? 'Principal',
            'email' => $administratorEmail,
            'password' => password_hash(
                $administratorPassword,
                PASSWORD_DEFAULT
            ),
        ]);

        echo "Usuario administrador creado correctamente.\n";
    }

    /**
     * Obtiene el identificador del rol requerido para el administrador.
     *
     * @throws RuntimeException Si los datos maestros no fueron cargados.
     */
    private function findAdministratorRoleId(PDO $databaseConnection): int
    {
        $administratorRoleStatement = $databaseConnection->prepare(
            'SELECT id
             FROM roles
             WHERE nombre = :nombre
             LIMIT 1'
        );
        $administratorRoleStatement->execute([
            'nombre' => 'Administrador',
        ]);

        $administratorRoleId = $administratorRoleStatement->fetchColumn();

        if ($administratorRoleId === false) {
            throw new RuntimeException(
                'No existe el rol Administrador. Ejecuta primero los datos maestros.'
            );
        }

        return (int) $administratorRoleId;
    }

    /**
     * Indica si el correo configurado ya pertenece a un usuario.
     */
    private function userExists(
        PDO $databaseConnection,
        string $administratorEmail
    ): bool {
        $userExistsStatement = $databaseConnection->prepare(
            'SELECT COUNT(*)
             FROM usuarios
             WHERE email = :email'
        );
        $userExistsStatement->execute([
            'email' => $administratorEmail,
        ]);

        return (int) $userExistsStatement->fetchColumn() > 0;
    }
}
