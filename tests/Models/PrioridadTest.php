<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Prioridad;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PrioridadTest extends TestCase
{
    /**
     * Lista únicamente prioridades activas para nuevas operaciones.
     */
    public function testListsOnlyActivePriorities(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $listPrioritiesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'WHERE activo = 1'
                )
            ))
            ->willReturn($listPrioritiesStatement);
        $listPrioritiesStatement->method('fetchAll')->willReturn([
            [
                'id' => '3',
                'nombre' => 'Alta',
                'nivel' => '3',
                'descripcion' => null,
                'color' => '#ff0000',
                'activo' => '1',
            ],
        ]);
        $priorityModel = new Prioridad($databaseConnection);

        $priorities = $priorityModel->active();

        self::assertCount(1, $priorities);
        self::assertSame(3, $priorities[0]['nivel']);
    }

    /**
     * Lista las prioridades por nivel y convierte los tipos devueltos por PDO.
     */
    public function testListsPrioritiesOrderedByLevel(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $listPrioritiesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('ORDER BY nivel ASC'))
            ->willReturn($listPrioritiesStatement);
        $listPrioritiesStatement->method('fetchAll')->willReturn([
            [
                'id' => '1',
                'nombre' => 'Baja',
                'nivel' => '1',
                'descripcion' => null,
                'color' => '#6c757d',
                'activo' => '1',
            ],
        ]);
        $priorityModel = new Prioridad($databaseConnection);

        $priorities = $priorityModel->all();

        self::assertSame(1, $priorities[0]['id']);
        self::assertSame(1, $priorities[0]['nivel']);
        self::assertNull($priorities[0]['descripcion']);
        self::assertTrue($priorities[0]['activo']);
    }

    /**
     * Busca una prioridad por identificador mediante una sentencia preparada.
     */
    public function testFindsPriorityById(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findPriorityStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findPriorityStatement);
        $findPriorityStatement->expects(self::once())
            ->method('execute')
            ->with(['prioridad_id' => 3])
            ->willReturn(true);
        $findPriorityStatement->method('fetch')->willReturn([
            'id' => '3',
            'nombre' => 'Alta',
            'nivel' => '3',
            'descripcion' => 'Atención pronta.',
            'color' => null,
            'activo' => '0',
        ]);
        $priorityModel = new Prioridad($databaseConnection);

        $priority = $priorityModel->findById(3);

        self::assertSame('Alta', $priority['nombre']);
        self::assertNull($priority['color']);
        self::assertFalse($priority['activo']);
    }

    /**
     * Comprueba un nivel único excluyendo la prioridad actualmente editada.
     */
    public function testChecksUniqueLevelExcludingEditedPriority(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $priorityExistsStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'nivel = :valor'
                ) && str_contains($query, 'id <> :prioridad_id')
            ))
            ->willReturn($priorityExistsStatement);
        $priorityExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 3,
                'prioridad_id' => 8,
            ])
            ->willReturn(true);
        $priorityExistsStatement->method('fetchColumn')->willReturn(0);
        $priorityModel = new Prioridad($databaseConnection);

        self::assertFalse($priorityModel->levelExists(3, 8));
    }

    /**
     * Inserta todos los campos configurables mediante parámetros.
     */
    public function testCreatesPriorityWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $createPriorityStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('INSERT INTO prioridades'))
            ->willReturn($createPriorityStatement);
        $createPriorityStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Crítica',
                'nivel' => 5,
                'descripcion' => null,
                'color' => '#ff0000',
            ])
            ->willReturn(true);
        $priorityModel = new Prioridad($databaseConnection);

        $priorityModel->create('Crítica', 5, null, '#ff0000');
    }

    /**
     * Actualiza una prioridad sin mezclar el cambio de estado lógico.
     */
    public function testUpdatesPriorityWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updatePriorityStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'SET nombre = :nombre'
                ) && !str_contains($query, 'activo')
            ))
            ->willReturn($updatePriorityStatement);
        $updatePriorityStatement->expects(self::once())
            ->method('execute')
            ->with([
                'prioridad_id' => 4,
                'nombre' => 'Urgente',
                'nivel' => 10,
                'descripcion' => 'Servicio bloqueado.',
                'color' => null,
            ])
            ->willReturn(true);
        $priorityModel = new Prioridad($databaseConnection);

        $priorityModel->update(4, 'Urgente', 10, 'Servicio bloqueado.', null);
    }

    /**
     * Desactiva una prioridad sin ejecutar una eliminación.
     */
    public function testUpdatesActiveStatusWithoutDeletingPriority(): void
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
                'prioridad_id' => 4,
                'activo' => 0,
            ])
            ->willReturn(true);
        $priorityModel = new Prioridad($databaseConnection);

        $priorityModel->updateActiveStatus(4, false);
    }
}
