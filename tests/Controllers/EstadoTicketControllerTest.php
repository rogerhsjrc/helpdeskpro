<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\EstadoTicketController;
use App\Core\Request;
use App\Core\Session;
use App\Models\EstadoTicket;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class EstadoTicketControllerTest extends TestCase
{
    /**
     * Restablece la sesión simulada antes de cada escenario.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión creados por la prueba finalizada.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Renderiza el listado escapando datos y mostrando el indicador final.
     */
    public function testListsTicketStatusesWithEscapedContent(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $listTicketStatusesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($listTicketStatusesStatement);
        $listTicketStatusesStatement->method('fetchAll')->willReturn([
            [
                'id' => '8',
                'nombre' => '<script>alert(1)</script>',
                'descripcion' => 'Estado <final>',
                'orden' => '8',
                'es_final' => '1',
                'activo' => '1',
            ],
        ]);
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->index(
            new Request('GET', '/admin/estados-ticket')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->content());
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $response->content()
        );
        self::assertStringContainsString(
            'action="/admin/estados-ticket/8/estado"',
            $response->content()
        );
        self::assertMatchesRegularExpression('/<td>Sí<\/td>/', $response->content());
    }

    /**
     * Muestra el formulario con límites coherentes y protección CSRF.
     */
    public function testShowsCreateTicketStatusForm(): void
    {
        $controller = new EstadoTicketController();

        $response = $controller->create(
            new Request('GET', '/admin/estados-ticket/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nuevo estado de ticket', $response->content());
        self::assertStringContainsString('max="255"', $response->content());
        self::assertStringContainsString('name="es_final"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Rechaza campos inválidos y un indicador final manipulado antes de consultar.
     */
    public function testRejectsInvalidTicketStatusBeforeDatabaseQuery(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->store(new Request(
            'POST',
            '/admin/estados-ticket',
            [],
            [
                'nombre' => '',
                'descripcion' => str_repeat('á', 256),
                'orden' => '0',
                'es_final' => ['1'],
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString('El nombre es obligatorio.', $response->content());
        self::assertStringContainsString(
            'El orden debe ser un entero entre 1 y 255.',
            $response->content()
        );
        self::assertStringContainsString(
            'El indicador de estado final no es válido.',
            $response->content()
        );
    }

    /**
     * Informa por separado los conflictos de nombre y orden.
     */
    public function testRejectsDuplicateNameAndOrder(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $nameExistsStatement = $this->createStub(PDOStatement::class);
        $orderExistsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $nameExistsStatement,
                $orderExistsStatement
            );
        $nameExistsStatement->method('fetchColumn')->willReturn(1);
        $orderExistsStatement->method('fetchColumn')->willReturn(1);
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->store(new Request(
            'POST',
            '/admin/estados-ticket',
            [],
            [
                'nombre' => 'Cerrado',
                'descripcion' => '',
                'orden' => '6',
                'es_final' => '1',
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString(
            'Ya existe un estado con ese nombre.',
            $response->content()
        );
        self::assertStringContainsString(
            'Ya existe un estado con ese orden.',
            $response->content()
        );
    }

    /**
     * Registra un estado no final cuando el checkbox no fue enviado.
     */
    public function testCreatesNonFinalTicketStatusAndRedirects(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $nameExistsStatement = $this->createStub(PDOStatement::class);
        $orderExistsStatement = $this->createStub(PDOStatement::class);
        $createTicketStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $nameExistsStatement,
                $orderExistsStatement,
                $createTicketStatusStatement
            );
        $nameExistsStatement->method('fetchColumn')->willReturn(0);
        $orderExistsStatement->method('fetchColumn')->willReturn(0);
        $createTicketStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'En revisión',
                'descripcion' => null,
                'orden' => 8,
                'es_final' => 0,
            ])
            ->willReturn(true);
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->store(new Request(
            'POST',
            '/admin/estados-ticket',
            [],
            [
                'nombre' => '  En revisión ',
                'descripcion' => '  ',
                'orden' => '8',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/estados-ticket', $response->headers()['Location']);
        self::assertSame(
            'Estado de ticket creado correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Actualiza un estado excluyéndolo de sus comprobaciones de unicidad.
     */
    public function testUpdatesExistingTicketStatusAsFinal(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findTicketStatusStatement = $this->createStub(PDOStatement::class);
        $nameExistsStatement = $this->createMock(PDOStatement::class);
        $orderExistsStatement = $this->createMock(PDOStatement::class);
        $updateTicketStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findTicketStatusStatement,
                $nameExistsStatement,
                $orderExistsStatement,
                $updateTicketStatusStatement
            );
        $findTicketStatusStatement->method('fetch')->willReturn([
            'id' => '5',
            'nombre' => 'Resuelto',
            'descripcion' => null,
            'orden' => '5',
            'es_final' => '0',
            'activo' => '1',
        ]);
        $nameExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 'Solucionado',
                'estado_id' => 5,
            ])
            ->willReturn(true);
        $orderExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 8,
                'estado_id' => 5,
            ])
            ->willReturn(true);
        $nameExistsStatement->method('fetchColumn')->willReturn(0);
        $orderExistsStatement->method('fetchColumn')->willReturn(0);
        $updateTicketStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'estado_id' => 5,
                'nombre' => 'Solucionado',
                'descripcion' => 'Trabajo completado.',
                'orden' => 8,
                'es_final' => 1,
            ])
            ->willReturn(true);
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->update(
            new Request(
                'POST',
                '/admin/estados-ticket/5/actualizar',
                [],
                [
                    'nombre' => 'Solucionado',
                    'descripcion' => 'Trabajo completado.',
                    'orden' => '8',
                    'es_final' => '1',
                ]
            ),
            '5'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            'Estado de ticket actualizado correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Desactiva un estado existente sin eliminarlo.
     */
    public function testDeactivatesExistingTicketStatus(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findTicketStatusStatement = $this->createStub(PDOStatement::class);
        $updateStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findTicketStatusStatement,
                $updateStatusStatement
            );
        $findTicketStatusStatement->method('fetch')->willReturn([
            'id' => '7',
            'nombre' => 'Cancelado',
            'descripcion' => null,
            'orden' => '7',
            'es_final' => '1',
            'activo' => '1',
        ]);
        $updateStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'estado_id' => 7,
                'activo' => 0,
            ])
            ->willReturn(true);
        $controller = new EstadoTicketController(
            new EstadoTicket($databaseConnection)
        );

        $response = $controller->updateStatus(
            new Request(
                'POST',
                '/admin/estados-ticket/7/estado',
                [],
                ['activo' => '0']
            ),
            '7'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            'Estado de ticket desactivado correctamente.',
            Session::pullFlash('success')
        );
    }
}
