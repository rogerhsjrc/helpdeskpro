<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\MiddlewareInterface;
use Closure;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterTest extends TestCase
{
    /**
     * Comprueba la resolución y decodificación de parámetros dinámicos.
     */
    public function testDispatchesGetRouteWithDecodedDynamicParameter(): void
    {
        $router = new Router();
        $router->get(
            '/tickets/{codigo}',
            static fn (Request $request, string $codigo): Response => Response::html($codigo)
        );

        $response = $router->dispatch(new Request('GET', '/tickets/TCK%20001'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('TCK 001', $response->content());
    }

    /**
     * Comprueba que una barra final no cambie la coincidencia de ruta.
     */
    public function testAcceptsRouteWithOrWithoutTrailingSlash(): void
    {
        $router = new Router();
        $router->get(
            '/tickets',
            static fn (Request $request): Response => Response::html('Listado')
        );

        self::assertSame(
            'Listado',
            $router->dispatch(new Request('GET', '/tickets/'))->content()
        );
    }

    /**
     * Comprueba la ejecución de middleware definido como callable.
     */
    public function testExecutesCallableMiddlewareBeforeHandler(): void
    {
        $router = new Router();
        $middleware = static function (Request $request, Closure $next): Response {
            return $next($request)->withHeader('X-Middleware', 'callable');
        };
        $router->get(
            '/privada',
            static fn (Request $request): Response => Response::html('Privada'),
            [$middleware]
        );

        $response = $router->dispatch(new Request('GET', '/privada'));

        self::assertSame('callable', $response->headers()['X-Middleware']);
    }

    /**
     * Comprueba la ejecución de una implementación de MiddlewareInterface.
     */
    public function testExecutesMiddlewareInterfaceBeforeHandler(): void
    {
        $router = new Router();
        $middleware = new class implements MiddlewareInterface {
            /**
             * Añade un header para evidenciar el paso por el middleware.
             */
            public function process(Request $request, Closure $next): Response
            {
                return $next($request)->withHeader('X-Middleware', 'interface');
            }
        };
        $router->get(
            '/privada',
            static fn (Request $request): Response => Response::html('Privada'),
            [$middleware]
        );

        $response = $router->dispatch(new Request('GET', '/privada'));

        self::assertSame('interface', $response->headers()['X-Middleware']);
    }

    /**
     * Comprueba la respuesta 405 y la enumeración de métodos permitidos.
     */
    public function testReturnsMethodNotAllowedWithAllowHeader(): void
    {
        $router = new Router();
        $router->get(
            '/tickets',
            static fn (Request $request): Response => Response::html('Listado')
        );
        $router->post(
            '/tickets',
            static fn (Request $request): Response => Response::html('Creado')
        );

        $response = $router->dispatch(new Request('DELETE', '/tickets'));

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET, POST', $response->headers()['Allow']);
    }

    /**
     * Comprueba la respuesta 404 predeterminada del Router.
     */
    public function testReturnsDefaultNotFoundResponse(): void
    {
        $response = (new Router())->dispatch(new Request('GET', '/no-existe'));

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString('Página no encontrada', $response->content());
    }

    /**
     * Comprueba el controlador 404 configurado por la aplicación.
     */
    public function testUsesConfiguredNotFoundHandler(): void
    {
        $router = new Router();
        $router->setNotFoundHandler(
            static fn (Request $request): Response => Response::html('No encontrada', 404)
        );

        $response = $router->dispatch(new Request('GET', '/no-existe'));

        self::assertSame(404, $response->statusCode());
        self::assertSame('No encontrada', $response->content());
    }

    /**
     * Comprueba el contrato de retorno obligatorio de los middleware.
     */
    public function testRejectsMiddlewareThatDoesNotReturnResponse(): void
    {
        $router = new Router();
        $router->get(
            '/invalida',
            static fn (Request $request): Response => Response::html('No ejecutada'),
            [static fn (Request $request, Closure $next): string => 'inválido']
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('El middleware debe devolver una instancia de Response.');

        $router->dispatch(new Request('GET', '/invalida'));
    }

    /**
     * Comprueba el contrato de retorno obligatorio de los controladores.
     */
    public function testRejectsHandlerThatDoesNotReturnResponse(): void
    {
        $router = new Router();
        $router->get(
            '/invalida',
            static fn (Request $request): string => 'inválido'
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('El controlador debe devolver una instancia de Response.');

        $router->dispatch(new Request('GET', '/invalida'));
    }

    /**
     * Comprueba el error explícito ante un controlador inexistente.
     */
    public function testRejectsUnknownControllerClass(): void
    {
        $router = new Router();
        $router->get('/invalida', ['App\Controllers\MissingController', 'index']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No existe el controlador');

        $router->dispatch(new Request('GET', '/invalida'));
    }
}
