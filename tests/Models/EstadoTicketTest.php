<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\EstadoTicket;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class EstadoTicketTest extends TestCase
{
    /**
     * Lista únicamente estados activos para el formulario de transiciones.
     */
    public function testListsOnlyActiveTicketStatuses(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $statement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('WHERE activo = 1'))
            ->willReturn($statement);
        $statement->method('fetchAll')->willReturn([[
            'id' => '1', 'codigo' => 'ABIERTO', 'nombre' => 'Abierto',
            'descripcion' => null, 'orden' => '1', 'es_final' => '0', 'activo' => '1',
        ]]);
        $model = new EstadoTicket($databaseConnection);

        $statuses = $model->active();

        self::assertCount(1, $statuses);
        self::assertSame('ABIERTO', $statuses[0]['codigo']);
    }

    /**
     * Resuelve el estado inicial mediante su código estable.
     */
    public function testFindsTicketStatusByStableCode(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('WHERE codigo = :codigo'))
            ->willReturn($findStatusStatement);
        $findStatusStatement->expects(self::once())
            ->method('execute')
            ->with(['codigo' => EstadoTicket::CODIGO_ABIERTO])
            ->willReturn(true);
        $findStatusStatement->method('fetch')->willReturn([
            'id' => '1',
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'descripcion' => null,
            'orden' => '1',
            'es_final' => '0',
            'activo' => '1',
        ]);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatus = $ticketStatusModel->findByCode(
            EstadoTicket::CODIGO_ABIERTO
        );

        self::assertSame(1, $ticketStatus['id']);
        self::assertSame('ABIERTO', $ticketStatus['codigo']);
    }

    /**
     * Expone códigos estables para que las reglas no dependan de nombres editables.
     */
    public function testDefinesStableSystemCodes(): void
    {
        self::assertSame('ABIERTO', EstadoTicket::CODIGO_ABIERTO);
        self::assertSame('ASIGNADO', EstadoTicket::CODIGO_ASIGNADO);
        self::assertSame('EN_PROCESO', EstadoTicket::CODIGO_EN_PROCESO);
        self::assertSame('PENDIENTE_CLIENTE', EstadoTicket::CODIGO_PENDIENTE_CLIENTE);
        self::assertSame('RESUELTO', EstadoTicket::CODIGO_RESUELTO);
        self::assertSame('CERRADO', EstadoTicket::CODIGO_CERRADO);
        self::assertSame('CANCELADO', EstadoTicket::CODIGO_CANCELADO);
    }

    /**
     * Lista los estados por orden y convierte los tipos devueltos por PDO.
     */
    public function testListsTicketStatusesOrderedByConfiguredOrder(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $listTicketStatusesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('ORDER BY orden ASC'))
            ->willReturn($listTicketStatusesStatement);
        $listTicketStatusesStatement->method('fetchAll')->willReturn([
            [
                'id' => '1',
                'codigo' => 'ABIERTO',
                'nombre' => 'Abierto',
                'descripcion' => null,
                'orden' => '1',
                'es_final' => '0',
                'activo' => '1',
            ],
        ]);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatuses = $ticketStatusModel->all();

        self::assertSame(1, $ticketStatuses[0]['id']);
        self::assertSame('ABIERTO', $ticketStatuses[0]['codigo']);
        self::assertSame(1, $ticketStatuses[0]['orden']);
        self::assertFalse($ticketStatuses[0]['es_final']);
        self::assertTrue($ticketStatuses[0]['activo']);
    }

    /**
     * Busca un estado por identificador mediante una sentencia preparada.
     */
    public function testFindsTicketStatusById(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findTicketStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findTicketStatusStatement);
        $findTicketStatusStatement->expects(self::once())
            ->method('execute')
            ->with(['estado_id' => 6])
            ->willReturn(true);
        $findTicketStatusStatement->method('fetch')->willReturn([
            'id' => '6',
            'codigo' => 'CERRADO',
            'nombre' => 'Cerrado',
            'descripcion' => 'Finalizado.',
            'orden' => '6',
            'es_final' => '1',
            'activo' => '0',
        ]);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatus = $ticketStatusModel->findById(6);

        self::assertSame('Cerrado', $ticketStatus['nombre']);
        self::assertTrue($ticketStatus['es_final']);
        self::assertFalse($ticketStatus['activo']);
    }

    /**
     * Comprueba un orden único excluyendo el estado actualmente editado.
     */
    public function testChecksUniqueOrderExcludingEditedTicketStatus(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatusExistsStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'orden = :valor'
                ) && str_contains($query, 'id <> :estado_id')
            ))
            ->willReturn($ticketStatusExistsStatement);
        $ticketStatusExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 6,
                'estado_id' => 6,
            ])
            ->willReturn(true);
        $ticketStatusExistsStatement->method('fetchColumn')->willReturn(0);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        self::assertFalse($ticketStatusModel->orderExists(6, 6));
    }

    /**
     * Comprueba la unicidad del código estable con una consulta preparada.
     */
    public function testChecksUniqueTicketStatusCode(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatusExistsStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('codigo = :valor'))
            ->willReturn($ticketStatusExistsStatement);
        $ticketStatusExistsStatement->expects(self::once())
            ->method('execute')
            ->with(['valor' => 'ABIERTO'])
            ->willReturn(true);
        $ticketStatusExistsStatement->method('fetchColumn')->willReturn(1);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        self::assertTrue($ticketStatusModel->codeExists('ABIERTO'));
    }

    /**
     * Inserta el indicador final como un entero compatible con MariaDB.
     */
    public function testCreatesFinalTicketStatusWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $createTicketStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('INSERT INTO estados_ticket'))
            ->willReturn($createTicketStatusStatement);
        $createTicketStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'codigo' => 'ARCHIVADO',
                'nombre' => 'Archivado',
                'descripcion' => null,
                'orden' => 8,
                'es_final' => 1,
            ])
            ->willReturn(true);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatusModel->create('ARCHIVADO', 'Archivado', null, 8, true);
    }

    /**
     * Actualiza los campos configurables sin mezclar el estado lógico.
     */
    public function testUpdatesTicketStatusWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updateTicketStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'es_final = :es_final'
                ) && !str_contains($query, 'activo')
            ))
            ->willReturn($updateTicketStatusStatement);
        $updateTicketStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'estado_id' => 5,
                'nombre' => 'Solucionado',
                'descripcion' => 'Solución aplicada.',
                'orden' => 5,
                'es_final' => 0,
            ])
            ->willReturn(true);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatusModel->update(
            5,
            'Solucionado',
            'Solución aplicada.',
            5,
            false
        );
    }

    /**
     * Desactiva un estado sin ejecutar una eliminación.
     */
    public function testUpdatesActiveStatusWithoutDeletingTicketStatus(): void
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
                'estado_id' => 7,
                'activo' => 0,
            ])
            ->willReturn(true);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatusModel->updateActiveStatus(7, false);
    }
}
