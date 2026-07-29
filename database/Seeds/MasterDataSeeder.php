<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Database;
use PDO;
use Throwable;

final class MasterDataSeeder
{
    /**
     * Crea los catálogos indispensables sin duplicar registros existentes.
     *
     * @throws Throwable Si una inserción falla y la transacción debe revertirse.
     */
    public function run(): void
    {
        $databaseConnection = Database::connection();
        $databaseConnection->beginTransaction();

        try {
            $this->seedRoles($databaseConnection);
            $this->seedCategories($databaseConnection);
            $this->seedPriorities($databaseConnection);
            $this->seedTicketStatuses($databaseConnection);
            $databaseConnection->commit();
        } catch (Throwable $exception) {
            if ($databaseConnection->inTransaction()) {
                $databaseConnection->rollBack();
            }

            throw $exception;
        }

        echo "Datos maestros verificados correctamente.\n";
    }

    /**
     * Registra los roles iniciales utilizados por la autorización.
     */
    private function seedRoles(PDO $databaseConnection): void
    {
        $roleDefinitions = [
            [
                'nombre' => 'Administrador',
                'descripcion' => 'Gestiona usuarios, configuraciones y todos los tickets.',
            ],
            [
                'nombre' => 'Técnico',
                'descripcion' => 'Atiende, comenta y resuelve los tickets asignados.',
            ],
            [
                'nombre' => 'Cliente',
                'descripcion' => 'Crea tickets y consulta el seguimiento de sus solicitudes.',
            ],
        ];

        $this->insertMissingRecords(
            $databaseConnection,
            'roles',
            'nombre',
            $roleDefinitions
        );
    }

    /**
     * Registra las categorías disponibles para clasificar tickets.
     */
    private function seedCategories(PDO $databaseConnection): void
    {
        $categoryDefinitions = [
            [
                'nombre' => 'Hardware',
                'descripcion' => 'Problemas relacionados con equipos, periféricos y componentes físicos.',
            ],
            [
                'nombre' => 'Software',
                'descripcion' => 'Errores, instalación o configuración de aplicaciones.',
            ],
            [
                'nombre' => 'Accesos',
                'descripcion' => 'Problemas de autenticación, permisos o credenciales.',
            ],
            [
                'nombre' => 'Redes',
                'descripcion' => 'Problemas de conectividad, internet o red interna.',
            ],
            [
                'nombre' => 'Otros',
                'descripcion' => 'Incidencias que no corresponden a las categorías anteriores.',
            ],
        ];

        $this->insertMissingRecords(
            $databaseConnection,
            'categorias',
            'nombre',
            $categoryDefinitions
        );
    }

    /**
     * Registra las prioridades ordenadas por nivel de impacto.
     */
    private function seedPriorities(PDO $databaseConnection): void
    {
        $priorityDefinitions = [
            [
                'nombre' => 'Baja',
                'nivel' => 1,
                'descripcion' => 'La incidencia no impide continuar trabajando.',
                'color' => '#6c757d',
            ],
            [
                'nombre' => 'Media',
                'nivel' => 2,
                'descripcion' => 'La incidencia afecta parcialmente el trabajo del usuario.',
                'color' => '#0d6efd',
            ],
            [
                'nombre' => 'Alta',
                'nivel' => 3,
                'descripcion' => 'La incidencia afecta una función importante y requiere atención pronta.',
                'color' => '#fd7e14',
            ],
            [
                'nombre' => 'Urgente',
                'nivel' => 4,
                'descripcion' => 'La incidencia bloquea completamente el trabajo o afecta un servicio crítico.',
                'color' => '#dc3545',
            ],
        ];

        $this->insertMissingRecords(
            $databaseConnection,
            'prioridades',
            'nombre',
            $priorityDefinitions
        );
    }

    /**
     * Registra los estados iniciales del ciclo de vida de un ticket.
     */
    private function seedTicketStatuses(PDO $databaseConnection): void
    {
        $ticketStatusDefinitions = [
            [
                'nombre' => 'Abierto',
                'descripcion' => 'El ticket fue creado y todavía no fue asignado.',
                'orden' => 1,
                'es_final' => 0,
            ],
            [
                'nombre' => 'Asignado',
                'descripcion' => 'El ticket fue asignado a un técnico.',
                'orden' => 2,
                'es_final' => 0,
            ],
            [
                'nombre' => 'En proceso',
                'descripcion' => 'El técnico se encuentra trabajando en la incidencia.',
                'orden' => 3,
                'es_final' => 0,
            ],
            [
                'nombre' => 'Pendiente del cliente',
                'descripcion' => 'Se requiere información o una acción del cliente.',
                'orden' => 4,
                'es_final' => 0,
            ],
            [
                'nombre' => 'Resuelto',
                'descripcion' => 'El técnico indicó que la incidencia fue solucionada.',
                'orden' => 5,
                'es_final' => 0,
            ],
            [
                'nombre' => 'Cerrado',
                'descripcion' => 'El ticket fue finalizado y no requiere más acciones.',
                'orden' => 6,
                'es_final' => 1,
            ],
            [
                'nombre' => 'Cancelado',
                'descripcion' => 'El ticket fue cancelado sin completar su resolución.',
                'orden' => 7,
                'es_final' => 1,
            ],
        ];

        $this->insertMissingRecords(
            $databaseConnection,
            'estados_ticket',
            'nombre',
            $ticketStatusDefinitions
        );
    }

    /**
     * Inserta únicamente definiciones cuyo valor único todavía no existe.
     *
     * Los nombres de tabla y columnas provienen exclusivamente de constantes
     * internas controladas por este seeder.
     *
     * @param list<array<string, int|string>> $recordDefinitions
     */
    private function insertMissingRecords(
        PDO $databaseConnection,
        string $tableName,
        string $uniqueColumnName,
        array $recordDefinitions
    ): void {
        if ($recordDefinitions === []) {
            return;
        }

        $columnNames = array_keys($recordDefinitions[0]);
        $columnList = implode(', ', $columnNames);
        $placeholderList = implode(
            ', ',
            array_map(
                static fn (string $columnName): string => ':' . $columnName,
                $columnNames
            )
        );
        $recordExistsStatement = $databaseConnection->prepare(
            sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s = :unique_value',
                $tableName,
                $uniqueColumnName
            )
        );
        $insertRecordStatement = $databaseConnection->prepare(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $tableName,
                $columnList,
                $placeholderList
            )
        );

        foreach ($recordDefinitions as $recordDefinition) {
            $recordExistsStatement->execute([
                'unique_value' => $recordDefinition[$uniqueColumnName],
            ]);

            if ((int) $recordExistsStatement->fetchColumn() > 0) {
                continue;
            }

            $insertRecordStatement->execute($recordDefinition);
        }
    }
}
