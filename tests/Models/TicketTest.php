<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\Ticket;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    /**
     * Cambia el estado aplicando parámetros explícitos para sus fechas derivadas.
     */
    public function testUpdatesStatusWithLifecycleEffects(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains($query, 'fecha_resolucion_at')
                    && str_contains($query, 'fecha_cierre_at')
            ))
            ->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with(
            self::callback(
                static fn (array $parameters): bool => $parameters['ticket_id'] === 12
                    && $parameters['estado_id'] === 5
                    && $parameters['codigo_objetivo_resolucion'] === 'RESUELTO'
            )
        )->willReturn(true);
        $ticketModel = new Ticket($databaseConnection);

        $ticketModel->updateStatus(12, 5, 'RESUELTO');
    }

    /**
     * Cambia la prioridad sin mezclar efectos del ciclo de vida.
     */
    public function testUpdatesTicketPriority(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with([
            'ticket_id' => 12,
            'prioridad_id' => 4,
        ])->willReturn(true);
        $ticketModel = new Ticket($databaseConnection);

        $ticketModel->updatePriority(12, 4);
    }

    /**
     * Actualiza técnico y fecha de la asignación sin modificar el estado.
     */
    public function testUpdatesAssignedTechnicianAndTimestamp(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'fecha_asignacion_at = NOW()'
                ) && !str_contains($query, 'estado_id')
            ))
            ->willReturn($statement);
        $statement->expects(self::once())->method('execute')->with([
            'ticket_id' => 12,
            'tecnico_id' => 4,
        ])->willReturn(true);
        $ticketModel = new Ticket($databaseConnection);

        $ticketModel->updateTechnician(12, 4);
    }

    /**
     * Bloquea el ticket visible antes de aplicar una actualización concurrente.
     */
    public function testFindsVisibleTicketForUpdateWithRowLock(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('FOR UPDATE'))
            ->willReturn($findStatement);
        $findStatement->method('fetch')->willReturn($this->ticketRow());
        $ticketModel = new Ticket($databaseConnection);

        $ticket = $ticketModel->findVisibleByCodeForUpdate(
            'HD-20260801-ABC123',
            1,
            'Administrador'
        );

        self::assertSame(12, $ticket['id']);
    }

    /**
     * Actualiza únicamente categoría, asunto y descripción mediante parámetros.
     */
    public function testUpdatesOriginalTicketContent(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $updateStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains($query, 'UPDATE tickets')
                    && !str_contains($query, 'prioridad_id')
                    && !str_contains($query, 'estado_id')
                    && !str_contains($query, 'tecnico_id')
            ))
            ->willReturn($updateStatement);
        $updateStatement->expects(self::once())
            ->method('execute')
            ->with([
                'ticket_id' => 12,
                'categoria_id' => 2,
                'asunto' => 'Nuevo asunto',
                'descripcion' => 'Nueva descripción',
            ])
            ->willReturn(true);
        $ticketModel = new Ticket($databaseConnection);

        $ticketModel->updateOriginal(12, 2, 'Nuevo asunto', 'Nueva descripción');
    }

    /**
     * Inserta un ticket sin técnico y devuelve la clave interna generada.
     */
    public function testCreatesTicketWithPreparedStatement(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $createTicketStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'INSERT INTO tickets'
                ) && !str_contains($query, 'tecnico_id')
            ))
            ->willReturn($createTicketStatement);
        $createTicketStatement->expects(self::once())
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
        $databaseConnection->method('lastInsertId')->willReturn('44');
        $ticketModel = new Ticket($databaseConnection);

        $ticketId = $ticketModel->create(
            'HD-20260801-ABC123',
            7,
            2,
            3,
            1,
            'No imprime',
            'La impresora no responde.'
        );

        self::assertSame(44, $ticketId);
    }

    /**
     * Limita tanto el conteo como el listado a los tickets propios del cliente.
     */
    public function testPaginatesOnlyClientOwnedTickets(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $countTicketsStatement = $this->createMock(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    't.cliente_id = :usuario_id'
                )
            ))
            ->willReturnOnConsecutiveCalls(
                $countTicketsStatement,
                $listTicketsStatement
            );
        $countTicketsStatement->expects(self::once())
            ->method('execute')
            ->with(['usuario_id' => 7])
            ->willReturn(true);
        $countTicketsStatement->method('fetchColumn')->willReturn(21);
        $boundParameters = [];
        $listTicketsStatement->method('bindValue')->willReturnCallback(
            static function (
                string|int $parameter,
                mixed $parameterValue,
                int $parameterType
            ) use (&$boundParameters): bool {
                $boundParameters[(string) $parameter] = [
                    'valor' => $parameterValue,
                    'tipo' => $parameterType,
                ];

                return true;
            }
        );
        $listTicketsStatement->method('fetchAll')->willReturn([
            $this->ticketRow(),
        ]);
        $ticketModel = new Ticket($databaseConnection);

        $pagination = $ticketModel->paginateVisibleTo(7, 'Cliente', 2, 10);

        self::assertSame(21, $pagination['total']);
        self::assertSame(2, $pagination['pagina_actual']);
        self::assertSame(3, $pagination['ultima_pagina']);
        self::assertSame('HD-20260801-ABC123', $pagination['tickets'][0]['codigo']);
        self::assertNull($pagination['tickets'][0]['tecnico']);
        self::assertSame(
            ['valor' => 7, 'tipo' => PDO::PARAM_INT],
            $boundParameters[':usuario_id']
        );
        self::assertSame(
            ['valor' => 10, 'tipo' => PDO::PARAM_INT],
            $boundParameters[':limite']
        );
        self::assertSame(
            ['valor' => 10, 'tipo' => PDO::PARAM_INT],
            $boundParameters[':desplazamiento']
        );
    }

    /**
     * Combina búsqueda literal y filtros dentro de consultas preparadas.
     */
    public function testPaginatesWithSearchAndCombinedFilters(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $countTicketsStatement = $this->createMock(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    'LOCATE(:busqueda_codigo, t.codigo)'
                ) && str_contains($query, 't.estado_id = :estado_id')
                    && str_contains($query, 't.prioridad_id = :prioridad_id')
                    && str_contains($query, 't.tecnico_id = :tecnico_id')
            ))
            ->willReturnOnConsecutiveCalls(
                $countTicketsStatement,
                $listTicketsStatement
            );
        $expectedParameters = [
            'busqueda_codigo' => '100% disponible',
            'busqueda_asunto' => '100% disponible',
            'estado_id' => 2,
            'prioridad_id' => 3,
            'tecnico_id' => 4,
        ];
        $countTicketsStatement->expects(self::once())
            ->method('execute')
            ->with($expectedParameters)
            ->willReturn(true);
        $countTicketsStatement->method('fetchColumn')->willReturn(0);
        $boundParameters = [];
        $listTicketsStatement->method('bindValue')->willReturnCallback(
            static function (
                string|int $parameter,
                mixed $parameterValue,
                int $parameterType
            ) use (&$boundParameters): bool {
                $boundParameters[(string) $parameter] = [
                    'valor' => $parameterValue,
                    'tipo' => $parameterType,
                ];

                return true;
            }
        );
        $listTicketsStatement->method('fetchAll')->willReturn([]);
        $ticketModel = new Ticket($databaseConnection);

        $pagination = $ticketModel->paginateVisibleTo(
            1,
            'Administrador',
            1,
            10,
            [
                'busqueda' => '100% disponible',
                'estado_id' => 2,
                'prioridad_id' => 3,
                'tecnico_id' => 4,
            ]
        );

        self::assertSame(0, $pagination['total']);
        self::assertSame(
            ['valor' => '100% disponible', 'tipo' => PDO::PARAM_STR],
            $boundParameters[':busqueda_codigo']
        );
        self::assertSame(
            ['valor' => 4, 'tipo' => PDO::PARAM_INT],
            $boundParameters[':tecnico_id']
        );
    }

    /**
     * Permite al administrador listar sin agregar una restricción por usuario.
     */
    public function testAdministratorCanListAllTickets(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $countTicketsStatement = $this->createStub(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains($query, '1 = 1')
                    && !str_contains($query, ':usuario_id')
            ))
            ->willReturnOnConsecutiveCalls(
                $countTicketsStatement,
                $listTicketsStatement
            );
        $countTicketsStatement->method('fetchColumn')->willReturn(0);
        $listTicketsStatement->method('fetchAll')->willReturn([]);
        $ticketModel = new Ticket($databaseConnection);

        $pagination = $ticketModel->paginateVisibleTo(1, 'Administrador', 50);

        self::assertSame(1, $pagination['pagina_actual']);
        self::assertSame(1, $pagination['ultima_pagina']);
        self::assertSame([], $pagination['tickets']);
    }

    /**
     * Busca el detalle técnico aplicando la asignación en la consulta preparada.
     */
    public function testFindsOnlyTicketAssignedToTechnician(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findTicketStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->with(self::callback(
                static fn (string $query): bool => str_contains(
                    $query,
                    't.tecnico_id = :usuario_id'
                ) && str_contains($query, 't.codigo = :ticket_codigo')
            ))
            ->willReturn($findTicketStatement);
        $findTicketStatement->expects(self::once())
            ->method('execute')
            ->with([
                'ticket_codigo' => 'HD-20260801-ABC123',
                'usuario_id' => 4,
            ])
            ->willReturn(true);
        $findTicketStatement->method('fetch')->willReturn($this->ticketRow([
            'id' => '15',
            'tecnico_id' => '4',
            'tecnico_nombre' => 'Ana',
            'tecnico_apellido' => 'Soporte',
        ]));
        $ticketModel = new Ticket($databaseConnection);

        $ticket = $ticketModel->findVisibleByCode(
            'HD-20260801-ABC123',
            4,
            'Técnico'
        );

        self::assertSame(15, $ticket['id']);
        self::assertSame('Ana', $ticket['tecnico']['nombre']);
    }

    /**
     * Devuelve null sin distinguir un ticket ajeno de uno inexistente.
     */
    public function testReturnsNullWhenTicketIsOutsideUserScope(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $findTicketStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findTicketStatement);
        $findTicketStatement->method('fetch')->willReturn(false);
        $ticketModel = new Ticket($databaseConnection);

        self::assertNull($ticketModel->findVisibleByCode(
            'HD-20260801-NOEXISTE',
            7,
            'Cliente'
        ));
    }

    /**
     * Rechaza roles desconocidos antes de ejecutar cualquier consulta.
     */
    public function testRejectsUnknownRoleBeforeQueryingDatabase(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $ticketModel = new Ticket($databaseConnection);

        $this->expectException(InvalidArgumentException::class);

        $ticketModel->paginateVisibleTo(1, 'Invitado', 1);
    }

    /**
     * Proporciona una fila relacional completa para comprobar el mapeo del modelo.
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
