<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\CategoriaController;
use App\Core\Request;
use App\Core\Session;
use App\Models\Categoria;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class CategoriaControllerTest extends TestCase
{
    /**
     * Restablece los mensajes y el token simulados antes de cada escenario.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión utilizados por la prueba finalizada.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Renderiza el listado escapando contenido y ofreciendo acciones por POST.
     */
    public function testListsCategoriesWithEscapedContentAndStatusAction(): void
    {
        $databaseConnection = $this->createStub(PDO::class);
        $listCategoriesStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->method('prepare')->willReturn($listCategoriesStatement);
        $listCategoriesStatement->method('fetchAll')->willReturn([
            [
                'id' => '8',
                'nombre' => '<script>alert(1)</script>',
                'descripcion' => 'Descripción <peligrosa>',
                'activo' => '1',
            ],
        ]);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->index(
            new Request('GET', '/admin/categorias')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->content());
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $response->content()
        );
        self::assertStringContainsString(
            'action="/admin/categorias/8/estado"',
            $response->content()
        );
        self::assertStringContainsString('name="_token"', $response->content());
    }

    /**
     * Muestra el formulario de alta con token CSRF y límites HTML coherentes.
     */
    public function testShowsCreateForm(): void
    {
        $controller = new CategoriaController();

        $response = $controller->create(
            new Request('GET', '/admin/categorias/crear')
        );

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Nueva categoría', $response->content());
        self::assertStringContainsString('maxlength="80"', $response->content());
        self::assertMatchesRegularExpression(
            '/name="_token"[\s\S]+value="[a-f0-9]{64}"/',
            $response->content()
        );
    }

    /**
     * Rechaza campos inválidos antes de realizar consultas y conserva sus valores.
     */
    public function testRejectsInvalidCreateInputBeforeDatabaseQuery(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/categorias',
            [],
            [
                'nombre' => '   ',
                'descripcion' => str_repeat('á', 256),
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString('El nombre es obligatorio.', $response->content());
        self::assertStringContainsString(
            'La descripción no puede superar los 255 caracteres.',
            $response->content()
        );
    }

    /**
     * Informa un nombre duplicado sin intentar insertar la categoría.
     */
    public function testRejectsDuplicateCategoryName(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryExistsStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($categoryExistsStatement);
        $categoryExistsStatement->method('fetchColumn')->willReturn(1);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/categorias',
            [],
            [
                'nombre' => 'Hardware',
                'descripcion' => '',
            ]
        ));

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString(
            'Ya existe una categoría con ese nombre.',
            $response->content()
        );
    }

    /**
     * Normaliza, registra y redirige después de una creación válida.
     */
    public function testCreatesCategoryAndRedirects(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $categoryExistsStatement = $this->createStub(PDOStatement::class);
        $createCategoryStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $categoryExistsStatement,
                $createCategoryStatement
            );
        $categoryExistsStatement->method('fetchColumn')->willReturn(0);
        $createCategoryStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Impresoras',
                'descripcion' => null,
            ])
            ->willReturn(true);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->store(new Request(
            'POST',
            '/admin/categorias',
            [],
            [
                'nombre' => '  Impresoras  ',
                'descripcion' => '   ',
            ]
        ));

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/categorias', $response->headers()['Location']);
        self::assertSame(
            'Categoría creada correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Devuelve 404 cuando la ruta de edición contiene un identificador inválido.
     */
    public function testReturnsNotFoundForInvalidEditIdentifier(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $databaseConnection->expects(self::never())->method('prepare');
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->edit(
            new Request('GET', '/admin/categorias/invalida/editar'),
            'invalida'
        );

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString(
            'La página solicitada no existe.',
            $response->content()
        );
    }

    /**
     * Actualiza una categoría excluyéndola de su propia validación de unicidad.
     */
    public function testUpdatesExistingCategoryAndRedirects(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findCategoryStatement = $this->createStub(PDOStatement::class);
        $categoryExistsStatement = $this->createMock(PDOStatement::class);
        $updateCategoryStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findCategoryStatement,
                $categoryExistsStatement,
                $updateCategoryStatement
            );
        $findCategoryStatement->method('fetch')->willReturn([
            'id' => '3',
            'nombre' => 'Accesos',
            'descripcion' => null,
            'activo' => '1',
        ]);
        $categoryExistsStatement->expects(self::once())
            ->method('execute')
            ->with([
                'nombre' => 'Credenciales',
                'categoria_id' => 3,
            ])
            ->willReturn(true);
        $categoryExistsStatement->method('fetchColumn')->willReturn(0);
        $updateCategoryStatement->expects(self::once())
            ->method('execute')
            ->with([
                'categoria_id' => 3,
                'nombre' => 'Credenciales',
                'descripcion' => 'Accesos y permisos.',
            ])
            ->willReturn(true);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->update(
            new Request(
                'POST',
                '/admin/categorias/3/actualizar',
                [],
                [
                    'nombre' => 'Credenciales',
                    'descripcion' => 'Accesos y permisos.',
                ]
            ),
            '3'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame('/admin/categorias', $response->headers()['Location']);
        self::assertSame(
            'Categoría actualizada correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Desactiva una categoría sin eliminar el registro.
     */
    public function testDeactivatesExistingCategory(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findCategoryStatement = $this->createStub(PDOStatement::class);
        $updateStatusStatement = $this->createMock(PDOStatement::class);
        $databaseConnection->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $findCategoryStatement,
                $updateStatusStatement
            );
        $findCategoryStatement->method('fetch')->willReturn([
            'id' => '4',
            'nombre' => 'Redes',
            'descripcion' => null,
            'activo' => '1',
        ]);
        $updateStatusStatement->expects(self::once())
            ->method('execute')
            ->with([
                'categoria_id' => 4,
                'activo' => 0,
            ])
            ->willReturn(true);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->updateStatus(
            new Request(
                'POST',
                '/admin/categorias/4/estado',
                [],
                ['activo' => '0']
            ),
            '4'
        );

        self::assertSame(303, $response->statusCode());
        self::assertSame(
            'Categoría desactivada correctamente.',
            Session::pullFlash('success')
        );
    }

    /**
     * Rechaza un valor de estado manipulado sin actualizar la categoría.
     */
    public function testRejectsInvalidCategoryStatus(): void
    {
        $databaseConnection = $this->createMock(PDO::class);
        $findCategoryStatement = $this->createStub(PDOStatement::class);
        $databaseConnection->expects(self::once())
            ->method('prepare')
            ->willReturn($findCategoryStatement);
        $findCategoryStatement->method('fetch')->willReturn([
            'id' => '4',
            'nombre' => 'Redes',
            'descripcion' => null,
            'activo' => '1',
        ]);
        $controller = new CategoriaController(new Categoria($databaseConnection));

        $response = $controller->updateStatus(
            new Request(
                'POST',
                '/admin/categorias/4/estado',
                [],
                ['activo' => 'eliminar']
            ),
            '4'
        );

        self::assertSame(422, $response->statusCode());
        self::assertStringContainsString(
            'El estado indicado no es válido.',
            $response->content()
        );
    }
}
