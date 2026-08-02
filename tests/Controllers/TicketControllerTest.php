<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\TicketController;
use App\Core\Request;
use App\Core\Session;
use App\Models\Categoria;
use App\Models\Prioridad;
use App\Models\EstadoTicket;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\TicketService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class TicketControllerTest extends TestCase
{
    /**
     * Restablece la identidad utilizada por cada escenario.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina la identidad de prueba al finalizar cada escenario.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Muestra al cliente los catálogos activos con token CSRF y salida escapada.
     */
    public function testShowsCreateFormToClient(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $categoryConnection = $this->createStub(PDO::class);
        $categoryStatement = $this->createStub(PDOStatement::class);
        $categoryConnection->method('prepare')->willReturn($categoryStatement);
        $categoryStatement->method('fetchAll')->willReturn([
            [
                'id' => '2',
                'nombre' => '<Hardware>',
                'descripcion' => null,
                'activo' => '1',
            ],
        ]);
        $priorityConnection = $this->createStub(PDO::class);
        $priorityStatement = $this->createStub(PDOStatement::class);
        $priorityConnection->method('prepare')->willReturn($priorityStatement);
        $priorityStatement->method('fetchAll')->willReturn([
            [
                'id' => '3',
                'nombre' => 'Alta',
                'nivel' => '3',
                'descripcion' => null,
                'color' => '#ff0000',
                'activo' => '1',
            ],
        ]);
        $controller = new TicketController(
            categoryModel: new Categoria($categoryConnection),
            priorityModel: new Prioridad($priorityConnection)
        );

        $response = $controller->create(
            new Request('GET', '/tickets/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nuevo ticket', $response->content());
        self::assertStringContainsString('&lt;Hardware&gt;', $response->content());
        self::assertStringContainsString('maxlength="180"', $response->content());
        self::assertStringContainsString('maxlength="5000"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Rechaza datos inválidos antes de invocar el servicio y conserva los valores.
     */
    public function testRejectsInvalidTicketCreationInput(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $categoryConnection = $this->createStub(PDO::class);
        $categoryStatement = $this->createStub(PDOStatement::class);
        $categoryConnection->method('prepare')->willReturn($categoryStatement);
        $categoryStatement->method('fetchAll')->willReturn([]);
        $priorityConnection = $this->createStub(PDO::class);
        $priorityStatement = $this->createStub(PDOStatement::class);
        $priorityConnection->method('prepare')->willReturn($priorityStatement);
        $priorityStatement->method('fetchAll')->willReturn([]);
        $serviceConnection = $this->createMock(PDO::class);
        $serviceConnection->expects(self::never())->method('beginTransaction');
        $controller = new TicketController(
            categoryModel: new Categoria($categoryConnection),
            priorityModel: new Prioridad($priorityConnection),
            ticketService: new TicketService($serviceConnection)
        );

        $response = $controller->store(new Request(
            'POST',
            '/tickets',
            [],
            [
                'categoria_id' => ['2'],
                'prioridad_id' => '0',
                'asunto' => '   ',
                'descripcion' => str_repeat('a', 5001),
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString(
            'Debe seleccionar una categoría válida.',
            $response->content()
        );
        self::assertStringContainsString('El asunto es obligatorio.', $response->content());
        self::assertStringContainsString(
            'La descripción no puede superar los 5000 caracteres.',
            $response->content()
        );
    }

    /**
     * Crea el ticket con valores normalizados y redirige a su código público.
     */
    public function testCreatesTicketAndRedirectsToDetail(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $databaseConnection = $this->createStub(PDO::class);
        $categoryStatement = $this->catalogRowStatement([
            'id' => '2',
            'nombre' => 'Hardware',
            'descripcion' => null,
            'activo' => '1',
        ]);
        $priorityStatement = $this->catalogRowStatement([
            'id' => '3',
            'nombre' => 'Alta',
            'nivel' => '3',
            'descripcion' => null,
            'color' => '#ff0000',
            'activo' => '1',
        ]);
        $statusStatement = $this->catalogRowStatement([
            'id' => '1',
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'descripcion' => null,
            'orden' => '1',
            'es_final' => '0',
            'activo' => '1',
        ]);
        $ticketStatement = $this->createMock(PDOStatement::class);
        $historyStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturnOnConsecutiveCalls(
            $categoryStatement,
            $priorityStatement,
            $statusStatement,
            $ticketStatement,
            $historyStatement
        );
        $databaseConnection->method('lastInsertId')->willReturn('44');
        $ticketStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(
                static fn (array $parameters): bool => $parameters['asunto']
                    === 'No imprime'
                    && $parameters['descripcion'] === 'Sin respuesta.'
                    && $parameters['cliente_id'] === 7
            ))
            ->willReturn(true);
        $ticketService = new TicketService(
            $databaseConnection,
            static fn (): string => 'HD-20260801-ABC123'
        );
        $controller = new TicketController(ticketService: $ticketService);

        $response = $controller->store(new Request(
            'POST',
            '/tickets',
            [],
            [
                'categoria_id' => ' 2 ',
                'prioridad_id' => ' 3 ',
                'asunto' => '  No imprime ',
                'descripcion' => '  Sin respuesta. ',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            '/tickets/HD-20260801-ABC123',
            $response->headers()['Location']
        );
        self::assertSame(
            'Ticket creado correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Mantiene el formulario cerrado cuando se invoca directamente con otro rol.
     */
    public function testRejectsCreateFormForNonClientRole(): void
    {
        Session::setUser($this->authenticatedUser('Administrador', 1, 1));
        $controller = new TicketController();

        $response = $controller->create(
            new Request('GET', '/tickets/crear')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Muestra al cliente el formulario de edición de un ticket propio editable.
     */
    public function testShowsEditableOriginalContentToClient(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $serviceConnection = $this->createStub(PDO::class);
        $ticketStatement = $this->createStub(PDOStatement::class);
        $serviceConnection->method('prepare')->willReturn($ticketStatement);
        $ticketStatement->method('fetch')->willReturn($this->ticketRow());
        $categoryConnection = $this->createStub(PDO::class);
        $categoryStatement = $this->createStub(PDOStatement::class);
        $categoryConnection->method('prepare')->willReturn($categoryStatement);
        $categoryStatement->method('fetchAll')->willReturn([
            [
                'id' => '2',
                'nombre' => 'Hardware',
                'descripcion' => null,
                'activo' => '1',
            ],
        ]);
        $controller = new TicketController(
            categoryModel: new Categoria($categoryConnection),
            ticketService: new TicketService($serviceConnection)
        );

        $response = $controller->edit(
            new Request('GET', '/tickets/HD-20260801-ABC123/editar'),
            'HD-20260801-ABC123'
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            'action="/tickets/HD-20260801-ABC123/actualizar"',
            $response->content()
        );
        self::assertStringContainsString('value="No imprime"', $response->content());
        self::assertStringNotContainsString('name="prioridad_id"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Muestra al administrador los técnicos activos para asignar el ticket.
     */
    public function testShowsTechnicianAssignmentFormToAdministrator(): void
    {
        Session::setUser($this->authenticatedUser());
        $serviceConnection = $this->createStub(PDO::class);
        $ticketStatement = $this->createStub(PDOStatement::class);
        $serviceConnection->method('prepare')->willReturn($ticketStatement);
        $ticketStatement->method('fetch')->willReturn($this->ticketRow());
        $userConnection = $this->createStub(PDO::class);
        $techniciansStatement = $this->createStub(PDOStatement::class);
        $userConnection->method('prepare')->willReturn($techniciansStatement);
        $techniciansStatement->method('fetchAll')->willReturn([[
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]]);
        $controller = new TicketController(
            ticketService: new TicketService($serviceConnection),
            userModel: new Usuario($userConnection)
        );

        $response = $controller->assignment(
            new Request('GET', '/tickets/HD-20260801-ABC123/asignar'),
            'HD-20260801-ABC123'
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Ana', $response->content());
        self::assertStringContainsString('name="tecnico_id"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Muestra al administrador estados y prioridades activos del flujo.
     */
    public function testShowsWorkflowFormToAdministrator(): void
    {
        Session::setUser($this->authenticatedUser());
        $serviceConnection = $this->createStub(PDO::class);
        $ticketStatement = $this->createStub(PDOStatement::class);
        $serviceConnection->method('prepare')->willReturn($ticketStatement);
        $ticketStatement->method('fetch')->willReturn($this->ticketRow());
        $statusConnection = $this->createStub(PDO::class);
        $statusStatement = $this->createStub(PDOStatement::class);
        $statusConnection->method('prepare')->willReturn($statusStatement);
        $statusStatement->method('fetchAll')->willReturn([[
            'id' => '1', 'codigo' => 'ABIERTO', 'nombre' => 'Abierto',
            'descripcion' => null, 'orden' => '1', 'es_final' => '0', 'activo' => '1',
        ]]);
        $priorityConnection = $this->createStub(PDO::class);
        $priorityStatement = $this->createStub(PDOStatement::class);
        $priorityConnection->method('prepare')->willReturn($priorityStatement);
        $priorityStatement->method('fetchAll')->willReturn([[
            'id' => '3', 'nombre' => 'Alta', 'nivel' => '3',
            'descripcion' => null, 'color' => '#ff0000', 'activo' => '1',
        ]]);
        $controller = new TicketController(
            priorityModel: new Prioridad($priorityConnection),
            ticketService: new TicketService($serviceConnection),
            ticketStatusModel: new EstadoTicket($statusConnection)
        );

        $response = $controller->workflow(
            new Request('GET', '/tickets/HD-20260801-ABC123/gestionar'),
            'HD-20260801-ABC123'
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('name="estado_id"', $response->content());
        self::assertStringContainsString('name="prioridad_id"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Renderiza el listado visible escapando el contenido dinámico del ticket.
     */
    public function testListsVisibleTicketsWithEscapedContent(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $databaseConnection = $this->createStub(PDO::class);
        $countTicketsStatement = $this->createStub(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturnOnConsecutiveCalls(
            $countTicketsStatement,
            $listTicketsStatement
        );
        $countTicketsStatement->method('fetchColumn')->willReturn(1);
        $listTicketsStatement->method('fetchAll')->willReturn([
            $this->ticketRow(['asunto' => '<script>alert(1)</script>']),
        ]);
        $controller = $this->listingController(new Ticket($databaseConnection));

        $response = $controller->index(new Request('GET', '/tickets'));

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->content());
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $response->content()
        );
        self::assertStringContainsString(
            'href="/tickets/HD-20260801-ABC123"',
            $response->content()
        );
        self::assertStringContainsString(
            '/tickets/HD-20260801-ABC123/editar',
            $response->content()
        );
        self::assertStringNotContainsString(
            '/tickets/HD-20260801-ABC123/asignar',
            $response->content()
        );
        self::assertStringNotContainsString(
            '/tickets/HD-20260801-ABC123/gestionar',
            $response->content()
        );
    }

    /**
     * Conserva búsqueda y filtros seleccionados al construir la paginación.
     */
    public function testListsTicketsWithFiltersPreservedInPagination(): void
    {
        Session::setUser($this->authenticatedUser('Administrador', 1, 1));
        $databaseConnection = $this->createStub(PDO::class);
        $countTicketsStatement = $this->createStub(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturnOnConsecutiveCalls(
            $countTicketsStatement,
            $listTicketsStatement
        );
        $countTicketsStatement->method('fetchColumn')->willReturn(21);
        $listTicketsStatement->method('fetchAll')->willReturn([
            $this->ticketRow([
                'tecnico_id' => '4',
                'tecnico_nombre' => 'Ana',
                'tecnico_apellido' => 'Soporte',
            ]),
        ]);
        $controller = $this->listingController(new Ticket($databaseConnection));

        $response = $controller->index(new Request('GET', '/tickets', [
            'busqueda' => '  No imprime  ',
            'estado_id' => '1',
            'prioridad_id' => '3',
            'tecnico_id' => '4',
            'pagina' => '2',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('value="No imprime"', $response->content());
        self::assertStringContainsString('Limpiar filtros', $response->content());
        self::assertStringContainsString(
            '/tickets?busqueda=No%20imprime&amp;estado_id=1&amp;prioridad_id=3'
                . '&amp;tecnico_id=4&amp;pagina=1',
            $response->content()
        );
        self::assertStringContainsString(
            '/tickets/HD-20260801-ABC123/editar',
            $response->content()
        );
        self::assertStringContainsString(
            '/tickets/HD-20260801-ABC123/asignar',
            $response->content()
        );
        self::assertStringContainsString(
            '/tickets/HD-20260801-ABC123/gestionar',
            $response->content()
        );
    }

    /**
     * Muestra al técnico únicamente la gestión del flujo de su ticket asignado.
     */
    public function testListsAssignedTicketWithTechnicianNavigation(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 4, 2));
        $databaseConnection = $this->createStub(PDO::class);
        $countTicketsStatement = $this->createStub(PDOStatement::class);
        $listTicketsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturnOnConsecutiveCalls(
            $countTicketsStatement,
            $listTicketsStatement
        );
        $countTicketsStatement->method('fetchColumn')->willReturn(1);
        $listTicketsStatement->method('fetchAll')->willReturn([
            $this->ticketRow([
                'tecnico_id' => '4',
                'tecnico_nombre' => 'Ana',
                'tecnico_apellido' => 'Soporte',
            ]),
        ]);
        $controller = $this->listingController(new Ticket($databaseConnection));

        $response = $controller->index(new Request('GET', '/tickets'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString(
            '/tickets/HD-20260801-ABC123/gestionar',
            $response->content()
        );
        self::assertStringNotContainsString(
            '/tickets/HD-20260801-ABC123/editar',
            $response->content()
        );
        self::assertStringNotContainsString(
            '/tickets/HD-20260801-ABC123/asignar',
            $response->content()
        );
    }

    /**
     * Muestra el detalle autorizado y conserva saltos de línea de forma segura.
     */
    public function testShowsVisibleTicketDetail(): void
    {
        Session::setUser($this->authenticatedUser('Técnico', 4, 2));
        $databaseConnection = $this->createStub(PDO::class);
        $findTicketStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findTicketStatement);
        $findTicketStatement->method('fetch')->willReturn($this->ticketRow([
            'id' => '15',
            'descripcion' => "Primera línea\n<script>alert(1)</script>",
            'tecnico_id' => '4',
            'tecnico_nombre' => 'Ana',
            'tecnico_apellido' => 'Soporte',
        ]));
        $controller = new TicketController(new Ticket($databaseConnection));

        $response = $controller->show(
            new Request('GET', '/tickets/HD-20260801-ABC123'),
            'HD-20260801-ABC123'
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('HD-20260801-ABC123', $response->content());
        self::assertStringContainsString('Primera línea<br', $response->content());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->content());
    }

    /**
     * Responde 404 sin consultar cuando el código público de ruta es inválido.
     */
    public function testReturnsNotFoundForInvalidTicketCode(): void
    {
        Session::setUser($this->authenticatedUser());
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new TicketController(new Ticket($databaseConnection));

        $response = $controller->show(
            new Request('GET', '/tickets/codigo%20invalido'),
            'codigo invalido'
        );

        self::assertSame(404, $response->statusCode());
    }

    /**
     * Responde 404 cuando el modelo no devuelve un ticket visible.
     */
    public function testReturnsNotFoundForTicketOutsideUserScope(): void
    {
        Session::setUser($this->authenticatedUser('Cliente', 7, 3));
        $databaseConnection = $this->createStub(PDO::class);
        $findTicketStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($findTicketStatement);
        $findTicketStatement->method('fetch')->willReturn(false);
        $controller = new TicketController(new Ticket($databaseConnection));

        $response = $controller->show(
            new Request('GET', '/tickets/HD-20260801-NOEXISTE'),
            'HD-20260801-NOEXISTE'
        );

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString(
            'La página solicitada no existe.',
            $response->content()
        );
    }

    /**
     * Mantiene el controlador cerrado si se invoca sin una sesión válida.
     */
    public function testRejectsDirectInvocationWithoutAuthenticatedUser(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new TicketController(new Ticket($databaseConnection));

        $response = $controller->index(new Request('GET', '/tickets'));

        self::assertSame(403, $response->statusCode());
        self::assertStringContainsString('Acceso denegado', $response->content());
    }

    /**
     * Proporciona una identidad mínima válida para el controlador.
     *
     * @return array{id: int, nombre: string, apellido: string, rol_id: int, rol: string}
     */
    private function authenticatedUser(
        string $roleName = 'Administrador',
        int $userId = 1,
        int $roleId = 1
    ): array {
        return [
            'id' => $userId,
            'nombre' => 'Usuario',
            'apellido' => 'Prueba',
            'rol_id' => $roleId,
            'rol' => $roleName,
        ];
    }

    /**
     * Construye una sentencia de catálogo con una fila controlada.
     *
     * @param array<string, mixed> $catalogRow
     */
    private function catalogRowStatement(array $catalogRow): PDOStatement
    {
        $catalogStatement = $this->createStub(PDOStatement::class);
        $catalogStatement->method('fetch')->willReturn($catalogRow);

        return $catalogStatement;
    }

    /**
     * Construye el controlador de listado con catálogos aislados de MariaDB.
     */
    private function listingController(Ticket $ticketModel): TicketController
    {
        $statusConnection = $this->createStub(PDO::class);
        $statusStatement = $this->createStub(PDOStatement::class);
        $statusConnection->method('prepare')->willReturn($statusStatement);
        $statusStatement->method('fetchAll')->willReturn([[
            'id' => '1',
            'codigo' => 'ABIERTO',
            'nombre' => 'Abierto',
            'descripcion' => null,
            'orden' => '1',
            'es_final' => '0',
            'activo' => '1',
        ]]);
        $priorityConnection = $this->createStub(PDO::class);
        $priorityStatement = $this->createStub(PDOStatement::class);
        $priorityConnection->method('prepare')->willReturn($priorityStatement);
        $priorityStatement->method('fetchAll')->willReturn([[
            'id' => '3',
            'nombre' => 'Alta',
            'nivel' => '3',
            'descripcion' => null,
            'color' => '#ff0000',
            'activo' => '1',
        ]]);
        $userConnection = $this->createStub(PDO::class);
        $userStatement = $this->createStub(PDOStatement::class);
        $userConnection->method('prepare')->willReturn($userStatement);
        $userStatement->method('fetchAll')->willReturn([[
            'id' => '4',
            'nombre' => 'Ana',
            'apellido' => 'Soporte',
            'email' => 'ana@example.test',
        ]]);

        return new TicketController(
            ticketModel: $ticketModel,
            priorityModel: new Prioridad($priorityConnection),
            userModel: new Usuario($userConnection),
            ticketStatusModel: new EstadoTicket($statusConnection)
        );
    }

    /**
     * Proporciona una fila relacional completa para renderizar las vistas.
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
