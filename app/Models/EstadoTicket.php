<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class EstadoTicket
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
     * Obtiene todos los estados ordenados según el ciclo de vida visible.
     *
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     orden: int,
     *     es_final: bool,
     *     activo: bool
     * }>
     */
    public function all(): array
    {
        $listTicketStatusesStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, descripcion, orden, es_final, activo
             FROM estados_ticket
             ORDER BY orden ASC'
        );
        $listTicketStatusesStatement->execute();
        $ticketStatusRows = $listTicketStatusesStatement->fetchAll();

        return array_map(
            fn (array $ticketStatusRow): array => $this->mapTicketStatus(
                $ticketStatusRow
            ),
            $ticketStatusRows
        );
    }

    /**
     * Busca un estado por identificador, aunque se encuentre inactivo.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     orden: int,
     *     es_final: bool,
     *     activo: bool
     * }|null
     */
    public function findById(int $ticketStatusId): ?array
    {
        $findTicketStatusStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, descripcion, orden, es_final, activo
             FROM estados_ticket
             WHERE id = :estado_id
             LIMIT 1'
        );
        $findTicketStatusStatement->execute([
            'estado_id' => $ticketStatusId,
        ]);
        $ticketStatusRow = $findTicketStatusStatement->fetch();

        return is_array($ticketStatusRow)
            ? $this->mapTicketStatus($ticketStatusRow)
            : null;
    }

    /**
     * Comprueba si el nombre ya pertenece a otro estado.
     */
    public function nameExists(
        string $ticketStatusName,
        ?int $excludedTicketStatusId = null
    ): bool {
        return $this->valueExists(
            'nombre',
            $ticketStatusName,
            $excludedTicketStatusId
        );
    }

    /**
     * Comprueba si el orden ya pertenece a otro estado.
     */
    public function orderExists(
        int $ticketStatusOrder,
        ?int $excludedTicketStatusId = null
    ): bool {
        return $this->valueExists(
            'orden',
            $ticketStatusOrder,
            $excludedTicketStatusId
        );
    }

    /**
     * Registra un estado activo con sus valores previamente validados.
     */
    public function create(
        string $ticketStatusName,
        ?string $ticketStatusDescription,
        int $ticketStatusOrder,
        bool $ticketStatusFinal
    ): void {
        $createTicketStatusStatement = $this->databaseConnection->prepare(
            'INSERT INTO estados_ticket (nombre, descripcion, orden, es_final)
             VALUES (:nombre, :descripcion, :orden, :es_final)'
        );
        $createTicketStatusStatement->execute([
            'nombre' => $ticketStatusName,
            'descripcion' => $ticketStatusDescription,
            'orden' => $ticketStatusOrder,
            'es_final' => $ticketStatusFinal ? 1 : 0,
        ]);
    }

    /**
     * Actualiza los campos configurables de un estado existente.
     */
    public function update(
        int $ticketStatusId,
        string $ticketStatusName,
        ?string $ticketStatusDescription,
        int $ticketStatusOrder,
        bool $ticketStatusFinal
    ): void {
        $updateTicketStatusStatement = $this->databaseConnection->prepare(
            'UPDATE estados_ticket
             SET nombre = :nombre,
                 descripcion = :descripcion,
                 orden = :orden,
                 es_final = :es_final
             WHERE id = :estado_id'
        );
        $updateTicketStatusStatement->execute([
            'estado_id' => $ticketStatusId,
            'nombre' => $ticketStatusName,
            'descripcion' => $ticketStatusDescription,
            'orden' => $ticketStatusOrder,
            'es_final' => $ticketStatusFinal ? 1 : 0,
        ]);
    }

    /**
     * Activa o desactiva un estado sin eliminar sus relaciones.
     */
    public function updateActiveStatus(
        int $ticketStatusId,
        bool $ticketStatusActive
    ): void {
        $updateStatusStatement = $this->databaseConnection->prepare(
            'UPDATE estados_ticket
             SET activo = :activo
             WHERE id = :estado_id'
        );
        $updateStatusStatement->execute([
            'estado_id' => $ticketStatusId,
            'activo' => $ticketStatusActive ? 1 : 0,
        ]);
    }

    /**
     * Comprueba un campo único controlado internamente y permite excluir un estado.
     */
    private function valueExists(
        string $columnName,
        string|int $columnValue,
        ?int $excludedTicketStatusId
    ): bool {
        $query = sprintf(
            'SELECT COUNT(*) FROM estados_ticket WHERE %s = :valor',
            $columnName
        );
        $parameters = [
            'valor' => $columnValue,
        ];

        if ($excludedTicketStatusId !== null) {
            $query .= ' AND id <> :estado_id';
            $parameters['estado_id'] = $excludedTicketStatusId;
        }

        $ticketStatusExistsStatement = $this->databaseConnection->prepare($query);
        $ticketStatusExistsStatement->execute($parameters);

        return (int) $ticketStatusExistsStatement->fetchColumn() > 0;
    }

    /**
     * Convierte una fila PDO a los tipos públicos utilizados por la aplicación.
     *
     * @param array<string, mixed> $ticketStatusRow
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     orden: int,
     *     es_final: bool,
     *     activo: bool
     * }
     */
    private function mapTicketStatus(array $ticketStatusRow): array
    {
        return [
            'id' => (int) $ticketStatusRow['id'],
            'nombre' => (string) $ticketStatusRow['nombre'],
            'descripcion' => $ticketStatusRow['descripcion'] === null
                ? null
                : (string) $ticketStatusRow['descripcion'],
            'orden' => (int) $ticketStatusRow['orden'],
            'es_final' => (bool) $ticketStatusRow['es_final'],
            'activo' => (bool) $ticketStatusRow['activo'],
        ];
    }
}
