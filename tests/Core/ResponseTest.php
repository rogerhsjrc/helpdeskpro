<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /**
     * Comprueba contenido, estado y tipo de una respuesta HTML.
     */
    public function testCreatesHtmlResponse(): void
    {
        $response = Response::html('<p>Creado</p>', 201);

        self::assertSame(201, $response->statusCode());
        self::assertSame('<p>Creado</p>', $response->content());
        self::assertSame(
            'text/html; charset=UTF-8',
            $response->headers()['Content-Type']
        );
    }

    /**
     * Comprueba la serialización JSON conservando caracteres Unicode.
     */
    public function testCreatesJsonResponseWithoutEscapingUnicode(): void
    {
        $response = Response::json(['mensaje' => 'Creación correcta']);

        self::assertSame('{"mensaje":"Creación correcta"}', $response->content());
        self::assertSame(
            'application/json; charset=UTF-8',
            $response->headers()['Content-Type']
        );
    }

    /**
     * Comprueba el estado y destino de una redirección.
     */
    public function testCreatesRedirectResponse(): void
    {
        $response = Response::redirect('/login', 303);

        self::assertSame(303, $response->statusCode());
        self::assertSame('', $response->content());
        self::assertSame('/login', $response->headers()['Location']);
    }

    /**
     * Comprueba que agregar un header preserve la inmutabilidad.
     */
    public function testAddingHeaderDoesNotModifyOriginalResponse(): void
    {
        $original = Response::html('Contenido');
        $modified = $original->withHeader('X-Test', 'ok');

        self::assertArrayNotHasKey('X-Test', $original->headers());
        self::assertSame('ok', $modified->headers()['X-Test']);
        self::assertSame($original->content(), $modified->content());
        self::assertSame($original->statusCode(), $modified->statusCode());
    }

    /**
     * Comprueba el envío del contenido y código de estado configurados.
     */
    public function testSendOutputsContentAndStatusCode(): void
    {
        $response = Response::html('respuesta enviada', 202);

        $this->expectOutputString('respuesta enviada');

        $response->send();

        self::assertSame(202, http_response_code());
    }
}
