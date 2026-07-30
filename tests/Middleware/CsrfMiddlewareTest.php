<?php

declare(strict_types=1);

namespace Tests\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    /**
     * Restablece los datos de sesión antes de cada validación.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos de sesión utilizados por la prueba.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Permite continuar cuando una solicitud modificadora presenta un token válido.
     */
    public function testAllowsPostRequestWithValidToken(): void
    {
        $token = Session::csrfToken();
        $request = new Request('POST', '/login', [], ['_token' => $token]);
        $middleware = new CsrfMiddleware();

        $response = $middleware->process(
            $request,
            static fn (Request $request): Response => Response::html('Procesada')
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Procesada', $response->content());
    }

    /**
     * Rechaza una solicitud modificadora que no incluye token CSRF.
     */
    public function testRejectsPostRequestWithoutToken(): void
    {
        $middleware = new CsrfMiddleware();

        $response = $middleware->process(
            new Request('POST', '/login'),
            static fn (Request $request): Response => Response::html('Procesada')
        );

        self::assertSame(403, $response->statusCode());
        self::assertStringContainsString(
            'La solicitud no pudo ser validada.',
            $response->content()
        );
    }

    /**
     * Rechaza una solicitud modificadora con un token CSRF incorrecto.
     */
    public function testRejectsPostRequestWithInvalidToken(): void
    {
        Session::csrfToken();
        $middleware = new CsrfMiddleware();

        $response = $middleware->process(
            new Request(
                'POST',
                '/login',
                [],
                ['_token' => str_repeat('b', 64)]
            ),
            static fn (Request $request): Response => Response::html('Procesada')
        );

        self::assertSame(403, $response->statusCode());
    }

    /**
     * Permite solicitudes de lectura sin exigir un token CSRF.
     */
    public function testAllowsSafeRequestWithoutToken(): void
    {
        $middleware = new CsrfMiddleware();

        $response = $middleware->process(
            new Request('GET', '/login'),
            static fn (Request $request): Response => Response::html('Formulario')
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame('Formulario', $response->content());
    }
}
