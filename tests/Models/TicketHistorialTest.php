<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\TicketHistorial;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class TicketHistorialTest extends TestCase
{
    /**
     * Audita un cambio de estado con valores legibles.
     */
    public function testRecordsWorkflowStateChange(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'CAMBIO_ESTADO'
                    && $parameters['valor_anterior'] === 'Asignado'
                    && $parameters['valor_nuevo'] === 'En proceso'
            )
        )->willReturn(true);
        $historyModel = new TicketHistorial($databaseConnection);

        $historyModel->recordWorkflowChange(
            12,
            4,
            TicketHistorial::TIPO_CAMBIO_ESTADO,
            'estado',
            'Asignado',
            'En proceso'
        );
    }

    /**
     * Distingue la primera asignación de una reasignación de técnico.
     */
    public function testRecordsFirstTechnicianAssignment(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with([
            'ticket_id' => 12,
            'usuario_id' => 1,
            'tipo_evento' => 'ASIGNACION',
            'campo_modificado' => 'tecnico',
            'valor_anterior' => null,
            'valor_nuevo' => 'Ana Soporte',
            'descripcion' => 'Se asignó un técnico al ticket.',
        ])->willReturn(true);
        $historyModel = new TicketHistorial($databaseConnection);

        $historyModel->recordTechnicianChange(12, 1, null, 'Ana Soporte');
    }

    /**
     * Registra el nombre anterior cuando el ticket se reasigna.
     */
    public function testRecordsTechnicianReassignment(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'CAMBIO_TECNICO'
                    && $parameters['valor_anterior'] === 'Juan Técnico'
                    && $parameters['valor_nuevo'] === 'Ana Soporte'
            )
        )->willReturn(true);
        $historyModel = new TicketHistorial($databaseConnection);

        $historyModel->recordTechnicianChange(
            12,
            1,
            'Juan Técnico',
            'Ana Soporte'
        );
    }

    /**
     * Registra los valores anterior y nuevo de un campo editado.
     */
    public function testRecordsEditedFieldValues(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($historyStatement);
        $historyStatement->expects(self::once())
            ->method('execute')
            ->with([
                'ticket_id' => 12,
                'usuario_id' => 1,
                'tipo_evento' => 'EDICION',
                'campo_modificado' => 'asunto',
                'valor_anterior' => 'Anterior',
                'valor_nuevo' => 'Nuevo',
                'descripcion' => 'Se modificó el campo asunto.',
            ])
            ->willReturn(true);
        $ticketHistoryModel = new TicketHistorial($databaseConnection);

        $ticketHistoryModel->recordFieldChange(12, 1, 'asunto', 'Anterior', 'Nuevo');
    }

    /**
     * Registra la creación con el ticket, actor y código público correspondientes.
     */
    public function testRecordsTicketCreationEvent(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('INSERT INTO ticket_historial'))
            ->willReturn($historyStatement);
        $historyStatement->expects(self::once())
            ->method('execute')
            ->with([
                'ticket_id' => 44,
                'usuario_id' => 7,
                'tipo_evento' => 'CREACION',
                'valor_nuevo' => 'HD-20260801-ABC123',
                'descripcion' => 'Ticket creado por el cliente.',
            ])
            ->willReturn(true);
        $ticketHistoryModel = new TicketHistorial($databaseConnection);

        $ticketHistoryModel->recordCreation(44, 7, 'HD-20260801-ABC123');
    }
}
