<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use PDO;
use RuntimeException;

final class DemoSeeder
{
    /**
     * Crea usuarios y un ticket demostrativo sin duplicarlos.
     */
    public function run(): void
    {
        $databaseConnection = Database::connection();
        $demoTicketCode = 'TCK-DEMO-0001';

        if ($this->ticketExists($databaseConnection, $demoTicketCode)) {
            echo "Los datos demostrativos ya existen.\n";

            return;
        }

        $clientId = $this->findOrCreateUser(
            $databaseConnection,
            'Cliente',
            'Cliente',
            'cliente.demo@helpdesk.local',
            'ClienteDemo123!'
        );
        $technicianId = $this->findOrCreateUser(
            $databaseConnection,
            'Técnico',
            'Técnico',
            'tecnico.demo@helpdesk.local',
            'TecnicoDemo123!'
        );
        $categoryId = $this->findMasterRecordId(
            $databaseConnection,
            'categorias',
            'Hardware'
        );
        $priorityId = $this->findMasterRecordId(
            $databaseConnection,
            'prioridades',
            'Alta'
        );
        $ticketStatusId = $this->findTicketStatusIdByCode(
            $databaseConnection,
            'ASIGNADO'
        );
        $insertTicketStatement = $databaseConnection->prepare(
            'INSERT INTO tickets (
                codigo,
                cliente_id,
                tecnico_id,
                categoria_id,
                prioridad_id,
                estado_id,
                asunto,
                descripcion,
                fecha_asignacion_at
            ) VALUES (
                :codigo,
                :cliente_id,
                :tecnico_id,
                :categoria_id,
                :prioridad_id,
                :estado_id,
                :asunto,
                :descripcion,
                NOW()
            )'
        );

        $insertTicketStatement->execute([
            'codigo' => $demoTicketCode,
            'cliente_id' => $clientId,
            'tecnico_id' => $technicianId,
            'categoria_id' => $categoryId,
            'prioridad_id' => $priorityId,
            'estado_id' => $ticketStatusId,
            'asunto' => 'Ticket demostrativo para validar relaciones',
            'descripcion' => 'Comprueba las relaciones principales del flujo de tickets.',
        ]);

        echo "Datos demostrativos creados correctamente.\n";
    }

    /**
     * Recupera un usuario demo o lo crea con el rol solicitado.
     */
    private function findOrCreateUser(
        PDO $databaseConnection,
        string $roleName,
        string $userName,
        string $userEmail,
        string $userPassword
    ): int {
        $findUserStatement = $databaseConnection->prepare(
            'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
        );
        $findUserStatement->execute(['email' => $userEmail]);
        $userId = $findUserStatement->fetchColumn();

        if ($userId !== false) {
            return (int) $userId;
        }

        $roleId = $this->findRoleId($databaseConnection, $roleName);
        $insertUserStatement = $databaseConnection->prepare(
            'INSERT INTO usuarios (
                rol_id,
                nombre,
                apellido,
                email,
                password,
                activo,
                email_verificado_at
            ) VALUES (
                :rol_id,
                :nombre,
                :apellido,
                :email,
                :password,
                1,
                NOW()
            )'
        );
        $insertUserStatement->execute([
            'rol_id' => $roleId,
            'nombre' => $userName,
            'apellido' => 'Demo',
            'email' => $userEmail,
            'password' => password_hash($userPassword, PASSWORD_DEFAULT),
        ]);

        return (int) $databaseConnection->lastInsertId();
    }

    /**
     * Obtiene el identificador de un rol por su nombre.
     *
     * @throws RuntimeException Si el rol solicitado no existe.
     */
    private function findRoleId(PDO $databaseConnection, string $roleName): int
    {
        $findRoleStatement = $databaseConnection->prepare(
            'SELECT id FROM roles WHERE nombre = :nombre LIMIT 1'
        );
        $findRoleStatement->execute(['nombre' => $roleName]);
        $roleId = $findRoleStatement->fetchColumn();

        if ($roleId === false) {
            throw new RuntimeException(
                sprintf('No existe el rol %s en la base de datos.', $roleName)
            );
        }

        return (int) $roleId;
    }

    /**
     * Obtiene un registro maestro por su nombre desde una tabla controlada.
     *
     * @throws RuntimeException Si el registro solicitado no existe.
     */
    private function findMasterRecordId(
        PDO $databaseConnection,
        string $tableName,
        string $recordName
    ): int {
        $allowedTableNames = [
            'categorias',
            'prioridades',
        ];

        if (!in_array($tableName, $allowedTableNames, true)) {
            throw new RuntimeException('La tabla maestra solicitada no está permitida.');
        }

        $findMasterRecordStatement = $databaseConnection->prepare(
            sprintf(
                'SELECT id FROM %s WHERE nombre = :nombre LIMIT 1',
                $tableName
            )
        );
        $findMasterRecordStatement->execute(['nombre' => $recordName]);
        $masterRecordId = $findMasterRecordStatement->fetchColumn();

        if ($masterRecordId === false) {
            throw new RuntimeException(
                sprintf('No existe el registro %s en %s.', $recordName, $tableName)
            );
        }

        return (int) $masterRecordId;
    }

    /**
     * Obtiene un estado del sistema mediante su código estable.
     *
     * @throws RuntimeException Si el código solicitado no existe.
     */
    private function findTicketStatusIdByCode(
        PDO $databaseConnection,
        string $ticketStatusCode
    ): int {
        $findTicketStatusStatement = $databaseConnection->prepare(
            'SELECT id FROM estados_ticket WHERE codigo = :codigo LIMIT 1'
        );
        $findTicketStatusStatement->execute([
            'codigo' => $ticketStatusCode,
        ]);
        $ticketStatusId = $findTicketStatusStatement->fetchColumn();

        if ($ticketStatusId === false) {
            throw new RuntimeException(
                sprintf(
                    'No existe el estado con código %s en la base de datos.',
                    $ticketStatusCode
                )
            );
        }

        return (int) $ticketStatusId;
    }

    /**
     * Indica si el ticket demostrativo ya fue creado.
     */
    private function ticketExists(
        PDO $databaseConnection,
        string $demoTicketCode
    ): bool {
        $ticketExistsStatement = $databaseConnection->prepare(
            'SELECT COUNT(*) FROM tickets WHERE codigo = :codigo'
        );
        $ticketExistsStatement->execute(['codigo' => $demoTicketCode]);

        return (int) $ticketExistsStatement->fetchColumn() > 0;
    }
}
