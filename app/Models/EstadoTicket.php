<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class EstadoTicket
{
    public const string CODIGO_ABIERTO = 'ABIERTO';

    public const string CODIGO_ASIGNADO = 'ASIGNADO';

    public const string CODIGO_EN_PROCESO = 'EN_PROCESO';

    public const string CODIGO_PENDIENTE_CLIENTE = 'PENDIENTE_CLIENTE';

    public const string CODIGO_RESUELTO = 'RESUELTO';

    public const string CODIGO_CERRADO = 'CERRADO';

    public const string CODIGO_CANCELADO = 'CANCELADO';

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
     *     codigo: string,
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
            'SELECT id, codigo, nombre, descripcion, orden, es_final, activo
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
     * Obtiene los estados activos ordenados para nuevas transiciones.
     *
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        $statement = $this->databaseConnection->prepare(
            'SELECT id, codigo, nombre, descripcion, orden, es_final, activo
             FROM estados_ticket
             WHERE activo = 1
             ORDER BY orden ASC'
        );
        $statement->execute();
        $rows = $statement->fetchAll();

        return array_map(
            fn (array $row): array => $this->mapTicketStatus($row),
            $rows
        );
    }

    /**
     * Busca un estado por identificador, aunque se encuentre inactivo.
     *
     * @return array{
     *     id: int,
     *     codigo: string,
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
            'SELECT id, codigo, nombre, descripcion, orden, es_final, activo
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
     * Busca un estado mediante su código estable, aunque se encuentre inactivo.
     *
     * @return array{
     *     id: int,
     *     codigo: string,
     *     nombre: string,
     *     descripcion: string|null,
     *     orden: int,
     *     es_final: bool,
     *     activo: bool
     * }|null
     */
    public function findByCode(string $ticketStatusCode): ?array
    {
        $findTicketStatusStatement = $this->databaseConnection->prepare(
            'SELECT id, codigo, nombre, descripcion, orden, es_final, activo
             FROM estados_ticket
             WHERE codigo = :codigo
             LIMIT 1'
        );
        $findTicketStatusStatement->execute([
            'codigo' => $ticketStatusCode,
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
     * Comprueba si el código interno ya pertenece a otro estado.
     */
    public function codeExists(string $ticketStatusCode): bool
    {
        return $this->valueExists('codigo', $ticketStatusCode, null);
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
        string $ticketStatusCode,
        string $ticketStatusName,
        ?string $ticketStatusDescription,
        int $ticketStatusOrder,
        bool $ticketStatusFinal
    ): void {
        $createTicketStatusStatement = $this->databaseConnection->prepare(
            'INSERT INTO estados_ticket (
                codigo,
                nombre,
                descripcion,
                orden,
                es_final
             ) VALUES (:codigo, :nombre, :descripcion, :orden, :es_final)'
        );
        $createTicketStatusStatement->execute([
            'codigo' => $ticketStatusCode,
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
     *     codigo: string,
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
            'codigo' => (string) $ticketStatusRow['codigo'],
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
