<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Prioridad
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
     * Obtiene todas las prioridades ordenadas por su nivel de impacto.
     *
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     nivel: int,
     *     descripcion: string|null,
     *     color: string|null,
     *     activo: bool
     * }>
     */
    public function all(): array
    {
        $listPrioritiesStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, nivel, descripcion, color, activo
             FROM prioridades
             ORDER BY nivel ASC'
        );
        $listPrioritiesStatement->execute();
        $priorityRows = $listPrioritiesStatement->fetchAll();

        return array_map(
            fn (array $priorityRow): array => $this->mapPriority($priorityRow),
            $priorityRows
        );
    }

    /**
     * Busca una prioridad por identificador sin excluir registros inactivos.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     nivel: int,
     *     descripcion: string|null,
     *     color: string|null,
     *     activo: bool
     * }|null
     */
    public function findById(int $priorityId): ?array
    {
        $findPriorityStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, nivel, descripcion, color, activo
             FROM prioridades
             WHERE id = :prioridad_id
             LIMIT 1'
        );
        $findPriorityStatement->execute([
            'prioridad_id' => $priorityId,
        ]);
        $priorityRow = $findPriorityStatement->fetch();

        return is_array($priorityRow) ? $this->mapPriority($priorityRow) : null;
    }

    /**
     * Comprueba si el nombre ya pertenece a otra prioridad.
     */
    public function nameExists(string $priorityName, ?int $excludedPriorityId = null): bool
    {
        return $this->valueExists(
            'nombre',
            $priorityName,
            $excludedPriorityId
        );
    }

    /**
     * Comprueba si el nivel ya pertenece a otra prioridad.
     */
    public function levelExists(int $priorityLevel, ?int $excludedPriorityId = null): bool
    {
        return $this->valueExists(
            'nivel',
            $priorityLevel,
            $excludedPriorityId
        );
    }

    /**
     * Registra una prioridad activa con valores previamente validados.
     */
    public function create(
        string $priorityName,
        int $priorityLevel,
        ?string $priorityDescription,
        ?string $priorityColor
    ): void {
        $createPriorityStatement = $this->databaseConnection->prepare(
            'INSERT INTO prioridades (nombre, nivel, descripcion, color)
             VALUES (:nombre, :nivel, :descripcion, :color)'
        );
        $createPriorityStatement->execute([
            'nombre' => $priorityName,
            'nivel' => $priorityLevel,
            'descripcion' => $priorityDescription,
            'color' => $priorityColor,
        ]);
    }

    /**
     * Actualiza los campos configurables de una prioridad existente.
     */
    public function update(
        int $priorityId,
        string $priorityName,
        int $priorityLevel,
        ?string $priorityDescription,
        ?string $priorityColor
    ): void {
        $updatePriorityStatement = $this->databaseConnection->prepare(
            'UPDATE prioridades
             SET nombre = :nombre,
                 nivel = :nivel,
                 descripcion = :descripcion,
                 color = :color
             WHERE id = :prioridad_id'
        );
        $updatePriorityStatement->execute([
            'prioridad_id' => $priorityId,
            'nombre' => $priorityName,
            'nivel' => $priorityLevel,
            'descripcion' => $priorityDescription,
            'color' => $priorityColor,
        ]);
    }

    /**
     * Activa o desactiva una prioridad sin eliminar sus relaciones.
     */
    public function updateActiveStatus(int $priorityId, bool $priorityActive): void
    {
        $updateStatusStatement = $this->databaseConnection->prepare(
            'UPDATE prioridades
             SET activo = :activo
             WHERE id = :prioridad_id'
        );
        $updateStatusStatement->execute([
            'prioridad_id' => $priorityId,
            'activo' => $priorityActive ? 1 : 0,
        ]);
    }

    /**
     * Comprueba un campo único controlado internamente y permite excluir un registro.
     */
    private function valueExists(
        string $columnName,
        string|int $columnValue,
        ?int $excludedPriorityId
    ): bool {
        $query = sprintf(
            'SELECT COUNT(*) FROM prioridades WHERE %s = :valor',
            $columnName
        );
        $parameters = [
            'valor' => $columnValue,
        ];

        if ($excludedPriorityId !== null) {
            $query .= ' AND id <> :prioridad_id';
            $parameters['prioridad_id'] = $excludedPriorityId;
        }

        $priorityExistsStatement = $this->databaseConnection->prepare($query);
        $priorityExistsStatement->execute($parameters);

        return (int) $priorityExistsStatement->fetchColumn() > 0;
    }

    /**
     * Convierte una fila PDO a los tipos públicos utilizados por la aplicación.
     *
     * @param array<string, mixed> $priorityRow
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     nivel: int,
     *     descripcion: string|null,
     *     color: string|null,
     *     activo: bool
     * }
     */
    private function mapPriority(array $priorityRow): array
    {
        return [
            'id' => (int) $priorityRow['id'],
            'nombre' => (string) $priorityRow['nombre'],
            'nivel' => (int) $priorityRow['nivel'],
            'descripcion' => $priorityRow['descripcion'] === null
                ? null
                : (string) $priorityRow['descripcion'],
            'color' => $priorityRow['color'] === null
                ? null
                : (string) $priorityRow['color'],
            'activo' => (bool) $priorityRow['activo'],
        ];
    }
}
