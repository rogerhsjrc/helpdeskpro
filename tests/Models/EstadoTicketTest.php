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
                'nombre' => 'Archivado',
                'descripcion' => null,
                'orden' => 8,
                'es_final' => 1,
            ])
            ->willReturn(true);
        $ticketStatusModel = new EstadoTicket($databaseConnection);

        $ticketStatusModel->create('Archivado', null, 8, true);
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
