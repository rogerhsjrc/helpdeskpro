<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\PrioridadController;
use App\Core\Request;
use App\Core\Session;
use App\Models\Prioridad;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PrioridadControllerTest extends TestCase
{
    /**
     * Restablece los datos de sesión antes de cada escenario.
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
     * Renderiza el listado escapando valores y ofreciendo el cambio lógico por POST.
     */
    public function testListsPrioritiesWithEscapedContent(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $listPrioritiesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($listPrioritiesStatement);
        $listPrioritiesStatement->method('fetchAll')->willReturn([
            [
                'id' => '9',
                'nombre' => '<script>alert(1)</script>',
                'nivel' => '5',
                'descripcion' => 'Impacto <alto>',
                'color' => '#ff0000',
                'activo' => '1',
            ],
        ]);
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->index(
            new Request('GET', '/admin/prioridades')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->content());
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $response->content()
        );
        self::assertStringContainsString(
            'action="/admin/prioridades/9/estado"',
            $response->content()
        );
    }

    /**
     * Muestra el formulario con límites coherentes y protección CSRF.
     */
    public function testShowsCreatePriorityForm(): void
    {
        $controller = new PrioridadController();

        $response = $controller->create(
            new Request('GET', '/admin/prioridades/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nueva prioridad', $response->content());
        self::assertStringContainsString('max="255"', $response->content());
        self::assertStringContainsString('pattern="#[0-9a-fA-F]{6}"', $response->content());
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Rechaza nombre, nivel, descripción y color inválidos antes de consultar.
     */
    public function testRejectsInvalidPriorityBeforeDatabaseQuery(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/prioridades',
            [],
            [
                'nombre' => '',
                'nivel' => '256',
                'descripcion' => str_repeat('á', 256),
                'color' => 'rojo',
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString('El nombre es obligatorio.', $response->content());
        self::assertStringContainsString(
            'El nivel debe ser un entero entre 1 y 255.',
            $response->content()
        );
        self::assertStringContainsString(
            'El color debe usar el formato hexadecimal #RRGGBB.',
            $response->content()
        );
    }

    /**
     * Informa por separado los conflictos de nombre y nivel.
     */
    public function testRejectsDuplicateNameAndLevel(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $nameExistsStatement = $this->createStub(PDOStatement::class);
        $levelExistsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $nameExistsStatement,
                $levelExistsStatement
            );
        $nameExistsStatement->method('fetchColumn')->willReturn(1);
        $levelExistsStatement->method('fetchColumn')->willReturn(1);
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/prioridades',
            [],
            [
                'nombre' => 'Urgente',
                'nivel' => '4',
                'descripcion' => '',
                'color' => '',
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString(
            'Ya existe una prioridad con ese nombre.',
            $response->content()
        );
        self::assertStringContainsString(
            'Ya existe una prioridad con ese nivel.',
            $response->content()
        );
    }

    /**
     * Normaliza y registra una prioridad válida antes de redirigir.
     */
    public function testCreatesPriorityAndRedirects(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $nameExistsStatement = $this->createStub(PDOStatement::class);
        $levelExistsStatement = $this->createStub(PDOStatement::class);
        $createPriorityStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $nameExistsStatement,
                $levelExistsStatement,
                $createPriorityStatement
            );
        $nameExistsStatement->method('fetchColumn')->willReturn(0);
        $levelExistsStatement->method('fetchColumn')->willReturn(0);
        $createPriorityStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Crítica',
                'nivel' => 5,
                'descripcion' => null,
                'color' => '#aabbcc',
            ])
            ->willReturn(true);
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/prioridades',
            [],
            [
                'nombre' => '  Crítica ',
                'nivel' => '5',
                'descripcion' => '  ',
                'color' => ' #AABBCC ',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/prioridades', $response->headers()['Location']);
        self::assertSame(
            'Prioridad creada correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Actualiza una prioridad excluyéndola de ambas comprobaciones de unicidad.
     */
    public function testUpdatesExistingPriority(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findPriorityStatement = $this->createStub(PDOStatement::class);
        $nameExistsStatement = $this->createMock(PDOStatement::class);
        $levelExistsStatement = $this->createMock(PDOStatement::class);
        $updatePriorityStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findPriorityStatement,
                $nameExistsStatement,
                $levelExistsStatement,
                $updatePriorityStatement
            );
        $findPriorityStatement->method('fetch')->willReturn([
            'id' => '4',
            'nombre' => 'Urgente',
            'nivel' => '4',
            'descripcion' => null,
            'color' => '#dc3545',
            'activo' => '1',
        ]);
        $nameExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 'Crítica',
                'prioridad_id' => 4,
            ])
            ->willReturn(true);
        $levelExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'valor' => 10,
                'prioridad_id' => 4,
            ])
            ->willReturn(true);
        $nameExistsStatement->method('fetchColumn')->willReturn(0);
        $levelExistsStatement->method('fetchColumn')->willReturn(0);
        $updatePriorityStatement->expects(self::once())
            ->method('execute')
            ->with([
                'prioridad_id' => 4,
                'nombre' => 'Crítica',
                'nivel' => 10,
                'descripcion' => 'Servicio detenido.',
                'color' => null,
            ])
            ->willReturn(true);
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->update(
            new Request(
                'POST',
                '/admin/prioridades/4/actualizar',
                [],
                [
                    'nombre' => 'Crítica',
                    'nivel' => '10',
                    'descripcion' => 'Servicio detenido.',
                    'color' => '',
                ]
            ),
            '4'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            'Prioridad actualizada correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Desactiva una prioridad existente sin eliminarla.
     */
    public function testDeactivatesExistingPriority(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findPriorityStatement = $this->createStub(PDOStatement::class);
        $updateStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findPriorityStatement,
                $updateStatusStatement
            );
        $findPriorityStatement->method('fetch')->willReturn([
            'id' => '2',
            'nombre' => 'Media',
            'nivel' => '2',
            'descripcion' => null,
            'color' => null,
            'activo' => '1',
        ]);
        $updateStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'prioridad_id' => 2,
                'activo' => 0,
            ])
            ->willReturn(true);
        $controller = new PrioridadController(new Prioridad($databaseConnection));

        $response = $controller->updateStatus(
            new Request(
                'POST',
                '/admin/prioridades/2/estado',
                [],
                ['activo' => '0']
            ),
            '2'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            'Prioridad desactivada correctamente.',
            Session::pullFlash('success')
        );
    }
}
