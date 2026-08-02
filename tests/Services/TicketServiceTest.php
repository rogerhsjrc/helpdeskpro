<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\TicketCreationException;
use App\Services\TicketService;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TicketServiceTest extends TestCase
{
    /**
     * Cambia estado y prioridad del técnico asignado dentro de una transacción.
     */
    public function testAssignedTechnicianUpdatesWorkflowAtomically(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow([
            'tecnico_id' => '4',
            'tecnico_nombre' => 'Ana',
            'tecnico_apellido' => 'Soporte',
            'estado_id' => '2',
            'estado_codigo' => 'ASIGNADO',
            'estado_nombre' => 'Asignado',
        ]));
        $statusStatement = $this->rowStatement($this->statusRow([
            'id' => '3',
            'codigo' => 'EN_PROCESO',
            'nombre' => 'En proceso',
            'orden' => '3',
        ]));
        $priorityStatement = $this->rowStatement($this->priorityRow([
            'id' => '4',
            'nombre' => 'Urgente',
            'nivel' => '4',
        ]));
        $statusUpdate = $this->createStub(PDOStatement::class);
        $statusHistory = $this->createMock(PDOStatement::class);
        $priorityUpdate = $this->createStub(PDOStatement::class);
        $priorityHistory = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(7))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $ticketStatement,
                $statusStatement,
                $priorityStatement,
                $statusUpdate,
                $statusHistory,
                $priorityUpdate,
                $priorityHistory
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $statusHistory->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'CAMBIO_ESTADO'
            )
        )->willReturn(true);
        $priorityHistory->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'CAMBIO_PRIORIDAD'
            )
        )->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $updated = $ticketService->updateWorkflow(
            'HD-20260801-ABC123',
            4,
            'Técnico',
            3,
            4
        );

        self::assertTrue($updated);
    }

    /**
     * Revierte una transición que salta directamente de abierto a cerrado.
     */
    public function testRejectsInvalidStateTransition(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow());
        $statusStatement = $this->rowStatement($this->statusRow([
            'id' => '6',
            'codigo' => 'CERRADO',
            'nombre' => 'Cerrado',
            'orden' => '6',
            'es_final' => '1',
        ]));
        $priorityStatement = $this->rowStatement($this->priorityRow());
        $databaseConnection->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $ticketStatement,
                $statusStatement,
                $priorityStatement
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('inTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('rollBack')->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $this->expectException(\App\Services\TicketUpdateException::class);
        $this->expectExceptionMessage('La transición de estado seleccionada no está permitida.');

        $ticketService->updateWorkflow(
            'HD-20260801-ABC123',
            1,
            'Administrador',
            6,
            3
        );
    }
    /**
     * Asigna un técnico activo y audita la operación sin cambiar el estado.
     */
    public function testAdministratorAssignsActiveTechnicianAtomically(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow());
        $technicianStatement = $this->rowStatement([
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]);
        $updateStatement = $this->createMock(PDOStatement::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $ticketStatement,
                $technicianStatement,
                $updateStatement,
                $historyStatement
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $updateStatement->expects(self::once())->method('execute')->with([
            'ticket_id' => 12,
            'tecnico_id' => 4,
        ])->willReturn(true);
        $historyStatement->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'ASIGNACION'
                    && $parameters['valor_nuevo'] === 'Ana Soporte'
            )
        )->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $assigned = $ticketService->assignTechnician(
            'HD-20260801-ABC123',
            1,
            'Administrador',
            4
        );

        self::assertTrue($assigned);
    }

    /**
     * Rechaza una asignación solicitada por un rol distinto del administrador.
     */
    public function testRejectsTechnicianAssignmentByClient(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('beginTransaction');
        $databaseConnection->expects(self::never())->method('prepare');
        $ticketService = new TicketService($databaseConnection);

        $this->expectException(\App\Services\TicketUpdateException::class);

        $ticketService->assignTechnician(
            'HD-20260801-ABC123',
            7,
            'Cliente',
            4
        );
    }

    /**
     * Evita actualización e historial cuando se selecciona al técnico actual.
     */
    public function testDoesNotWriteWhenTechnicianIsAlreadyAssigned(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow([
            'tecnico_id' => '4',
            'tecnico_nombre' => 'Ana',
            'tecnico_apellido' => 'Soporte',
        ]));
        $technicianStatement = $this->rowStatement([
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($ticketStatement, $technicianStatement);
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $assigned = $ticketService->assignTechnician(
            'HD-20260801-ABC123',
            1,
            'Administrador',
            4
        );

        self::assertFalse($assigned);
    }

    /**
     * Permite consultar la edición de un ticket propio abierto y sin asignar.
     */
    public function testClientCanEditOwnOpenUnassignedTicket(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow());
        $databaseConnection->method('prepare')->willReturn($ticketStatement);
        $ticketService = new TicketService($databaseConnection);

        $ticket = $ticketService->findEditableOriginal(
            'HD-20260801-ABC123',
            7,
            'Cliente'
        );

        self::assertSame(12, $ticket['id']);
    }

    /**
     * Impide al cliente editar su ticket después de una asignación técnica.
     */
    public function testClientCannotEditAssignedTicket(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow([
            'tecnico_id' => '4',
            'tecnico_nombre' => 'Ana',
            'tecnico_apellido' => 'Soporte',
        ]));
        $databaseConnection->method('prepare')->willReturn($ticketStatement);
        $ticketService = new TicketService($databaseConnection);

        try {
            $ticketService->findEditableOriginal(
                'HD-20260801-ABC123',
                7,
                'Cliente'
            );
            self::fail('Se esperaba el rechazo del ticket asignado.');
        } catch (\App\Services\TicketUpdateException $exception) {
            self::assertSame(403, $exception->statusCode());
            self::assertSame('autorizacion', $exception->field());
        }
    }

    /**
     * Permite al administrador actualizar y auditar únicamente el campo cambiado.
     */
    public function testAdministratorUpdatesAndAuditsChangedOriginalField(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow());
        $categoryStatement = $this->rowStatement($this->categoryRow());
        $updateStatement = $this->createMock(PDOStatement::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $ticketStatement,
                $categoryStatement,
                $updateStatement,
                $historyStatement
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $updateStatement->expects(self::once())
            ->method('execute')
            ->with([
                'ticket_id' => 12,
                'categoria_id' => 2,
                'asunto' => 'Nuevo asunto',
                'descripcion' => 'La impresora no responde.',
            ])
            ->willReturn(true);
        $historyStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (array $parameters): bool => $parameters['tipo_evento']
                    === 'EDICION'
                    && $parameters['campo_modificado'] === 'asunto'
                    && $parameters['valor_anterior'] === 'No imprime'
                    && $parameters['valor_nuevo'] === 'Nuevo asunto'
            ))
            ->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $updated = $ticketService->updateOriginal(
            'HD-20260801-ABC123',
            1,
            'Administrador',
            2,
            'Nuevo asunto',
            'La impresora no responde.'
        );

        self::assertTrue($updated);
    }

    /**
     * Confirma sin actualizar ni auditar cuando todos los valores son iguales.
     */
    public function testDoesNotWriteHistoryWhenOriginalContentDidNotChange(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $ticketStatement = $this->rowStatement($this->ticketRow());
        $categoryStatement = $this->rowStatement($this->categoryRow());
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($ticketStatement, $categoryStatement);
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $ticketService = new TicketService($databaseConnection);

        $updated = $ticketService->updateOriginal(
            'HD-20260801-ABC123',
            7,
            'Cliente',
            2,
            'No imprime',
            'La impresora no responde.'
        );

        self::assertFalse($updated);
    }
    /**
     * Crea y audita el ticket dentro de una misma transacción confirmada.
     */
    public function testCreatesClientTicketAndHistoryAtomically(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryStatement = $this->rowStatement($this->categoryRow());
        $priorityStatement = $this->rowStatement($this->priorityRow());
        $statusStatement = $this->rowStatement($this->statusRow());
        $ticketStatement = $this->createMock(PDOStatement::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(5))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $categoryStatement,
                $priorityStatement,
                $statusStatement,
                $ticketStatement,
                $historyStatement
            );
        $databaseConnection->expects(self::once())
            ->method('beginTransaction')
            ->willReturn(true);
        $databaseConnection->expects(self::once())
            ->method('commit')
            ->willReturn(true);
        $databaseConnection->expects(self::never())->method('rollBack');
        $databaseConnection->method('lastInsertId')->willReturn('44');
        $ticketStatement->expects(self::once())
            ->method('execute')
            ->with([
                'codigo' => 'HD-20260801-ABC123',
                'cliente_id' => 7,
                'categoria_id' => 2,
                'prioridad_id' => 3,
                'estado_id' => 1,
                'asunto' => 'No imprime',
                'descripcion' => 'La impresora no responde.',
            ])
            ->willReturn(true);
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
        $ticketService = new TicketService(
            $databaseConnection,
            static fn (): string => 'HD-20260801-ABC123'
        );

        $ticketCode = $ticketService->createForClient(
            7,
            'Cliente',
            2,
            3,
            'No imprime',
            'La impresora no responde.'
        );

        self::assertSame('HD-20260801-ABC123', $ticketCode);
    }

    /**
     * Revierte la operación cuando la categoría dejó de estar activa.
     */
    public function testRollsBackWhenCategoryIsNotAvailable(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryStatement = $this->rowStatement($this->categoryRow([
            'activo' => '0',
        ]));
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($categoryStatement);
        $databaseConnection->expects(self::once())
            ->method('beginTransaction')
            ->willReturn(true);
        $databaseConnection->expects(self::once())
            ->method('inTransaction')
            ->willReturn(true);
        $databaseConnection->expects(self::once())
            ->method('rollBack')
            ->willReturn(true);
        $databaseConnection->expects(self::never())->method('commit');
        $ticketService = new TicketService($databaseConnection);

        try {
            $ticketService->createForClient(
                7,
                'Cliente',
                2,
                3,
                'No imprime',
                'La impresora no responde.'
            );
            self::fail('Se esperaba una excepción para la categoría inactiva.');
        } catch (TicketCreationException $exception) {
            self::assertSame('categoria_id', $exception->field());
            self::assertSame(
                'La categoría seleccionada no está disponible.',
                $exception->getMessage()
            );
        }
    }

    /**
     * Reintenta con un código nuevo cuando la restricción única detecta colisión.
     */
    public function testRetriesTicketCodeCollisionBeforeAuditing(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryStatement = $this->rowStatement($this->categoryRow());
        $priorityStatement = $this->rowStatement($this->priorityRow());
        $statusStatement = $this->rowStatement($this->statusRow());
        $firstTicketStatement = $this->createStub(PDOStatement::class);
        $secondTicketStatement = $this->createMock(PDOStatement::class);
        $historyStatement = $this->createMock(PDOStatement::class);
        $collision = new PDOException('Código duplicado', 23000);
        $collision->errorInfo = [
            '23000',
            1062,
            "Duplicate entry for key 'uq_tickets_codigo'",
        ];
        $firstTicketStatement->method('execute')->willThrowException($collision);
        $databaseConnection->expects(self::exactly(6))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $categoryStatement,
                $priorityStatement,
                $statusStatement,
                $firstTicketStatement,
                $secondTicketStatement,
                $historyStatement
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->expects(self::once())->method('commit')->willReturn(true);
        $databaseConnection->method('lastInsertId')->willReturn('45');
        $secondTicketStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (array $parameters): bool => $parameters['codigo']
                    === 'HD-20260801-DEF456'
            ))
            ->willReturn(true);
        $historyStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (array $parameters): bool => $parameters['valor_nuevo']
                    === 'HD-20260801-DEF456'
            ))
            ->willReturn(true);
        $generatedCodes = [
            'HD-20260801-ABC123',
            'HD-20260801-DEF456',
        ];
        $ticketService = new TicketService(
            $databaseConnection,
            static function () use (&$generatedCodes): string {
                return (string) array_shift($generatedCodes);
            }
        );

        $ticketCode = $ticketService->createForClient(
            7,
            'Cliente',
            2,
            3,
            'No imprime',
            'La impresora no responde.'
        );

        self::assertSame('HD-20260801-DEF456', $ticketCode);
    }

    /**
     * Revierte el ticket si el evento de auditoría no puede registrarse.
     */
    public function testRollsBackWhenCreationHistoryFails(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryStatement = $this->rowStatement($this->categoryRow());
        $priorityStatement = $this->rowStatement($this->priorityRow());
        $statusStatement = $this->rowStatement($this->statusRow());
        $ticketStatement = $this->createStub(PDOStatement::class);
        $historyStatement = $this->createStub(PDOStatement::class);
        $historyStatement->method('execute')->willThrowException(
            new RuntimeException('Fallo de auditoría simulado.')
        );
        $databaseConnection->expects(self::exactly(5))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $categoryStatement,
                $priorityStatement,
                $statusStatement,
                $ticketStatement,
                $historyStatement
            );
        $databaseConnection->method('beginTransaction')->willReturn(true);
        $databaseConnection->method('lastInsertId')->willReturn('44');
        $databaseConnection->expects(self::once())
            ->method('inTransaction')
            ->willReturn(true);
        $databaseConnection->expects(self::once())
            ->method('rollBack')
            ->willReturn(true);
        $databaseConnection->expects(self::never())->method('commit');
        $ticketService = new TicketService(
            $databaseConnection,
            static fn (): string => 'HD-20260801-ABC123'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fallo de auditoría simulado.');

        $ticketService->createForClient(
            7,
            'Cliente',
            2,
            3,
            'No imprime',
            'La impresora no responde.'
        );
    }

    /**
     * Rechaza roles distintos de cliente antes de abrir una transacción.
     */
    public function testRejectsNonClientRoleBeforeTransaction(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('beginTransaction');
        $databaseConnection->expects(self::never())->method('prepare');
        $ticketService = new TicketService($databaseConnection);

        $this->expectException(TicketCreationException::class);

        $ticketService->createForClient(
            1,
            'Administrador',
            2,
            3,
            'No imprime',
            'La impresora no responde.'
        );
    }

    /**
     * Construye una sentencia que devuelve una fila de catálogo controlada.
     *
     * @param array<string, mixed> $row
     */
    private function rowStatement(array $row): PDOStatement
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('fetch')->willReturn($row);

        return $statement;
    }

    /**
     * Proporciona una categoría válida para el alta.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function categoryRow(array $overrides = []): array
    {
        return array_replace([
            'id' => '2',
            'nombre' => 'Hardware',
            'descripcion' => null,
            'activo' => '1',
        ], $overrides);
    }

    /**
     * Proporciona una prioridad válida para el alta.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function priorityRow(array $overrides = []): array
    {
        return array_replace([
            'id' => '3',
            'nombre' => 'Alta',
            'nivel' => '3',
            'descripcion' => null,
            'color' => '#ff0000',
            'activo' => '1',
        ], $overrides);
    }

    /**
     * Proporciona el estado inicial activo requerido por el servicio.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function statusRow(array $overrides = []): array
    {
        return array_replace([
            'id' => '1',
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'descripcion' => null,
            'orden' => '1',
            'es_final' => '0',
            'activo' => '1',
        ], $overrides);
    }

    /**
     * Proporciona un ticket relacional para evaluar permisos y cambios.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function ticketRow(array $overrides = []): array
    {
        return array_replace([
            'id' => '12',
            'codigo' => 'HD-20260801-ABC123',
            'asunto' => 'No imprime',
            'descripcion' => 'La impresora no responde.',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
            'fecha_asignacion_at' => null,
            'fecha_resolucion_at' => null,
            'fecha_cierre_at' => null,
            'cliente_id' => '7',
            'cliente_nombre' => 'Carla',
            'cliente_apellido' => 'Cliente',
            'cliente_email' => 'carla@example.test',
            'tecnico_id' => null,
            'tecnico_nombre' => null,
            'tecnico_apellido' => null,
            'categoria_id' => '2',
            'categoria_nombre' => 'Hardware',
            'categoria_activa' => '1',
            'prioridad_id' => '3',
            'prioridad_nombre' => 'Alta',
            'prioridad_color' => '#ff0000',
            'prioridad_activa' => '1',
            'estado_id' => '1',
            'estado_codigo' => 'ABIERTO',
            'estado_nombre' => 'Abierto',
            'estado_es_final' => '0',
            'estado_activo' => '1',
        ], $overrides);
    }
}
