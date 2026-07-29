<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $originalServer = [];

    /**
     * @var array<string, mixed>
     */
    private array $originalQuery = [];

    /**
     * @var array<string, mixed>
     */
    private array $originalBody = [];

    /**
     * @var array<string, mixed>
     */
    private array $originalFiles = [];

    /**
     * Conserva las superglobales antes de modificarlas en una prueba.
     */
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalQuery = $_GET;
        $this->originalBody = $_POST;
        $this->originalFiles = $_FILES;
    }

    /**
     * Restaura las superglobales originales después de cada prueba.
     */
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_GET = $this->originalQuery;
        $_POST = $this->originalBody;
        $_FILES = $this->originalFiles;
    }

    /**
     * Comprueba la normalización del método, URI y ruta.
     */
    public function testNormalizesMethodAndPath(): void
    {
        $request = new Request('post', '/tickets/15/?page=2');

        self::assertSame('POST', $request->method());
        self::assertSame('/tickets/15', $request->path());
        self::assertSame('/tickets/15/?page=2', $request->uri());
        self::assertTrue($request->isMethod('POST'));
        self::assertTrue($request->isMethod('post'));
    }

    /**
     * Comprueba el acceso a query, formulario, archivos y valores por defecto.
     */
    public function testReturnsQueryBodyAndFilesWithDefaults(): void
    {
        $file = ['name' => 'captura.png'];
        $request = new Request(
            'POST',
            '/tickets',
            ['page' => '2'],
            ['asunto' => 'Impresora'],
            ['adjunto' => $file]
        );

        self::assertSame(['page' => '2'], $request->query());
        self::assertSame('2', $request->query('page'));
        self::assertSame('predeterminado', $request->query('missing', 'predeterminado'));
        self::assertSame(['asunto' => 'Impresora'], $request->input());
        self::assertSame('Impresora', $request->input('asunto'));
        self::assertSame($file, $request->file('adjunto'));
        self::assertNull($request->file('missing'));
    }

    /**
     * Comprueba la lectura uniforme de headers HTTP y de contenido.
     */
    public function testReadsRegularAndContentHeaders(): void
    {
        $request = new Request(
            'POST',
            '/',
            server: [
                'HTTP_X_REQUEST_ID' => 'abc-123',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        self::assertSame('abc-123', $request->header('X-Request-Id'));
        self::assertSame('application/json', $request->header('Content-Type'));
        self::assertSame('default', $request->header('Missing', 'default'));
    }

    /**
     * Comprueba la captura de una petición desde las superglobales de PHP.
     */
    public function testCaptureBuildsRequestFromSuperglobals(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/capturada?filtro=abierto',
            'HTTP_ACCEPT' => 'text/html',
        ];
        $_GET = ['filtro' => 'abierto'];
        $_POST = ['asunto' => 'Teclado'];
        $_FILES = ['adjunto' => ['name' => 'foto.png']];

        $request = Request::capture();

        self::assertSame('POST', $request->method());
        self::assertSame('/capturada', $request->path());
        self::assertSame('abierto', $request->query('filtro'));
        self::assertSame('Teclado', $request->input('asunto'));
        self::assertSame('foto.png', $request->file('adjunto')['name']);
        self::assertSame('text/html', $request->header('Accept'));
    }
}
