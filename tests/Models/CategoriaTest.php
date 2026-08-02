<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Categoria;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class CategoriaTest extends TestCase
{
    /**
     * Lista únicamente categorías activas para formularios operativos.
     */
    public function testListsOnlyActiveCategories(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $listCategoriesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'WHERE activo = 1'
                )
            ))
            ->willReturn($listCategoriesStatement);
        $listCategoriesStatement->method('fetchAll')->willReturn([
            [
                'id' => '2',
                'nombre' => 'Hardware',
                'descripcion' => null,
                'activo' => '1',
            ],
        ]);
        $categoryModel = new Categoria($databaseConnection);

        $categories = $categoryModel->active();

        self::assertCount(1, $categories);
        self::assertTrue($categories[0]['activo']);
    }

    /**
     * Lista y convierte todas las categorías utilizando el orden alfabético.
     */
    public function testListsCategoriesWithApplicationTypes(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $listCategoriesStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'ORDER BY nombre ASC'
                )
            ))
            ->willReturn($listCategoriesStatement);
        $listCategoriesStatement->expects(self::once())
            ->method('execute')
            ->with()
            ->willReturn(true);
        $listCategoriesStatement->method('fetchAll')->willReturn([
            [
                'id' => '2',
                'nombre' => 'Hardware',
                'descripcion' => null,
                'activo' => '1',
            ],
            [
                'id' => '5',
                'nombre' => 'Otros',
                'descripcion' => 'Consultas generales.',
                'activo' => '0',
            ],
        ]);
        $categoryModel = new Categoria($databaseConnection);

        $categories = $categoryModel->all();

        self::assertSame(2, $categories[0]['id']);
        self::assertTrue($categories[0]['activo']);
        self::assertNull($categories[0]['descripcion']);
        self::assertFalse($categories[1]['activo']);
    }

    /**
     * Busca una categoría por identificador mediante una consulta preparada.
     */
    public function testFindsCategoryById(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findCategoryStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findCategoryStatement);
        $findCategoryStatement->expects(self::once())
            ->method('execute')
            ->with(['categoria_id' => 4])
            ->willReturn(true);
        $findCategoryStatement->method('fetch')->willReturn([
            'id' => '4',
            'nombre' => 'Redes',
            'descripcion' => 'Conectividad.',
            'activo' => '1',
        ]);
        $categoryModel = new Categoria($databaseConnection);

        $category = $categoryModel->findById(4);

        self::assertSame('Redes', $category['nombre']);
        self::assertTrue($category['activo']);
    }

    /**
     * Devuelve null cuando el identificador no corresponde a una categoría.
     */
    public function testReturnsNullWhenCategoryDoesNotExist(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findCategoryStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findCategoryStatement);
        $findCategoryStatement->method('fetch')->willReturn(false);
        $categoryModel = new Categoria($databaseConnection);

        self::assertNull($categoryModel->findById(99));
    }

    /**
     * Excluye el registro actual al comprobar el nombre durante una edición.
     */
    public function testChecksUniqueNameExcludingEditedCategory(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryExistsStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'id <> :categoria_id'
                )
            ))
            ->willReturn($categoryExistsStatement);
        $categoryExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Hardware',
                'categoria_id' => 2,
            ])
            ->willReturn(true);
        $categoryExistsStatement->method('fetchColumn')->willReturn(0);
        $categoryModel = new Categoria($databaseConnection);

        self::assertFalse($categoryModel->nameExists('Hardware', 2));
    }

    /**
     * Inserta una categoría usando parámetros y conserva la descripción nula.
     */
    public function testCreatesCategoryWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $createCategoryStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('INSERT INTO categorias'))
            ->willReturn($createCategoryStatement);
        $createCategoryStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Impresoras',
                'descripcion' => null,
            ])
            ->willReturn(true);
        $categoryModel = new Categoria($databaseConnection);

        $categoryModel->create('Impresoras', null);
    }

    /**
     * Actualiza los datos editables sin modificar directamente el estado.
     */
    public function testUpdatesCategoryWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updateCategoryStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'SET nombre = :nombre'
                ) && !str_contains($query, 'activo')
            ))
            ->willReturn($updateCategoryStatement);
        $updateCategoryStatement->expects(self::once())
            ->method('execute')
            ->with([
                'categoria_id' => 3,
                'nombre' => 'Credenciales',
                'descripcion' => 'Accesos y permisos.',
            ])
            ->willReturn(true);
        $categoryModel = new Categoria($databaseConnection);

        $categoryModel->update(3, 'Credenciales', 'Accesos y permisos.');
    }

    /**
     * Desactiva una categoría mediante actualización lógica parametrizada.
     */
    public function testUpdatesActiveStatusWithoutDeletingCategory(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updateStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'SET activo = :activo'
                ) && !str_contains($query, 'DELETE')
            ))
            ->willReturn($updateStatusStatement);
        $updateStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'categoria_id' => 5,
                'activo' => 0,
            ])
            ->willReturn(true);
        $categoryModel = new Categoria($databaseConnection);

        $categoryModel->updateActiveStatus(5, false);
    }
}
