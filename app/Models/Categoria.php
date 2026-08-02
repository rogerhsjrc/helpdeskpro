<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Categoria
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
     * Obtiene todas las categorías ordenadas alfabéticamente para su administración.
     *
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     activo: bool
     * }>
     */
    public function all(): array
    {
        $listCategoriesStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, descripcion, activo
             FROM categorias
             ORDER BY nombre ASC'
        );
        $listCategoriesStatement->execute();
        $categoryRows = $listCategoriesStatement->fetchAll();

        return array_map(
            fn (array $categoryRow): array => $this->mapCategory($categoryRow),
            $categoryRows
        );
    }

    /**
     * Obtiene las categorías seleccionables para nuevas operaciones.
     *
     * @return list<array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     activo: bool
     * }>
     */
    public function active(): array
    {
        $listActiveCategoriesStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, descripcion, activo
             FROM categorias
             WHERE activo = 1
             ORDER BY nombre ASC'
        );
        $listActiveCategoriesStatement->execute();
        $categoryRows = $listActiveCategoriesStatement->fetchAll();

        return array_map(
            fn (array $categoryRow): array => $this->mapCategory($categoryRow),
            $categoryRows
        );
    }

    /**
     * Busca una categoría por su identificador, independientemente de su estado.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     activo: bool
     * }|null
     */
    public function findById(int $categoryId): ?array
    {
        $findCategoryStatement = $this->databaseConnection->prepare(
            'SELECT id, nombre, descripcion, activo
             FROM categorias
             WHERE id = :categoria_id
             LIMIT 1'
        );
        $findCategoryStatement->execute([
            'categoria_id' => $categoryId,
        ]);
        $categoryRow = $findCategoryStatement->fetch();

        return is_array($categoryRow) ? $this->mapCategory($categoryRow) : null;
    }

    /**
     * Comprueba la unicidad del nombre y permite excluir la categoría editada.
     */
    public function nameExists(string $categoryName, ?int $excludedCategoryId = null): bool
    {
        $query = 'SELECT COUNT(*)
                  FROM categorias
                  WHERE nombre = :nombre';
        $parameters = [
            'nombre' => $categoryName,
        ];

        if ($excludedCategoryId !== null) {
            $query .= ' AND id <> :categoria_id';
            $parameters['categoria_id'] = $excludedCategoryId;
        }

        $categoryExistsStatement = $this->databaseConnection->prepare($query);
        $categoryExistsStatement->execute($parameters);

        return (int) $categoryExistsStatement->fetchColumn() > 0;
    }

    /**
     * Registra una categoría activa con sus valores ya validados.
     */
    public function create(string $categoryName, ?string $categoryDescription): void
    {
        $createCategoryStatement = $this->databaseConnection->prepare(
            'INSERT INTO categorias (nombre, descripcion)
             VALUES (:nombre, :descripcion)'
        );
        $createCategoryStatement->execute([
            'nombre' => $categoryName,
            'descripcion' => $categoryDescription,
        ]);
    }

    /**
     * Actualiza los datos editables de una categoría existente.
     */
    public function update(
        int $categoryId,
        string $categoryName,
        ?string $categoryDescription
    ): void {
        $updateCategoryStatement = $this->databaseConnection->prepare(
            'UPDATE categorias
             SET nombre = :nombre,
                 descripcion = :descripcion
             WHERE id = :categoria_id'
        );
        $updateCategoryStatement->execute([
            'categoria_id' => $categoryId,
            'nombre' => $categoryName,
            'descripcion' => $categoryDescription,
        ]);
    }

    /**
     * Activa o desactiva lógicamente una categoría sin eliminarla.
     */
    public function updateActiveStatus(int $categoryId, bool $categoryActive): void
    {
        $updateStatusStatement = $this->databaseConnection->prepare(
            'UPDATE categorias
             SET activo = :activo
             WHERE id = :categoria_id'
        );
        $updateStatusStatement->execute([
            'categoria_id' => $categoryId,
            'activo' => $categoryActive ? 1 : 0,
        ]);
    }

    /**
     * Convierte una fila PDO a los tipos públicos utilizados por la aplicación.
     *
     * @param array<string, mixed> $categoryRow
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     descripcion: string|null,
     *     activo: bool
     * }
     */
    private function mapCategory(array $categoryRow): array
    {
        return [
            'id' => (int) $categoryRow['id'],
            'nombre' => (string) $categoryRow['nombre'],
            'descripcion' => $categoryRow['descripcion'] === null
                ? null
                : (string) $categoryRow['descripcion'],
            'activo' => (bool) $categoryRow['activo'],
        ];
    }
}
