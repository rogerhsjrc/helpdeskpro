<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Closure;
use LogicException;
use RuntimeException;

final class Router
{
    /**
     * @var list<array{
     *     method: string,
     *     pattern: string,
     *     handler: callable|array{class-string, string},
     *     middleware: list<MiddlewareInterface|callable>
     * }>
     */
    private array $routes = [];

    /**
     * @var callable|array{class-string, string}|null
     */
    private mixed $notFoundHandler = null;

    /**
     * Registra una ruta que responde a solicitudes GET.
     *
     * @param callable|array{class-string, string} $handler
     * @param list<MiddlewareInterface|callable> $middleware
     */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * Registra una ruta que responde a solicitudes POST.
     *
     * @param callable|array{class-string, string} $handler
     * @param list<MiddlewareInterface|callable> $middleware
     */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Registra una ruta para un método HTTP específico.
     *
     * @param callable|array{class-string, string} $handler
     * @param list<MiddlewareInterface|callable> $middleware
     */
    public function add(
        string $method,
        string $path,
        callable|array $handler,
        array $middleware = []
    ): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compilePath($path),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Configura el controlador utilizado cuando ninguna ruta coincide.
     *
     * @param callable|array{class-string, string} $handler
     */
    public function setNotFoundHandler(callable|array $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    /**
     * Resuelve la petición, ejecuta su pipeline y devuelve una respuesta.
     */
    public function dispatch(Request $request): Response
    {
        $methodNotAllowed = false;

        foreach ($this->routes as $route) {
            $matches = [];

            if (preg_match($route['pattern'], $request->path(), $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $methodNotAllowed = true;

                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn (string|int $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );
            $parameters = array_map(
                static fn (string $value): string => rawurldecode($value),
                $parameters
            );

            return $this->runRoute(
                $request,
                $route['handler'],
                array_values($parameters),
                $route['middleware']
            );
        }

        if ($methodNotAllowed) {
            return Response::html(
                '<h1>405</h1><p>Método no permitido.</p>',
                405
            )->withHeader('Allow', $this->allowedMethodsFor($request->path()));
        }

        if ($this->notFoundHandler !== null) {
            return $this->invokeHandler($this->notFoundHandler, $request);
        }

        return Response::html('<h1>404</h1><p>Página no encontrada.</p>', 404);
    }

    /**
     * Construye y ejecuta el pipeline de middleware de la ruta encontrada.
     *
     * El pipeline utiliza una composición de closures similar a capas
     * envolventes. Para una ruta configurada con `[Auth, Csrf]`, el resultado
     * conceptual es:
     *
     * `Auth(Request, Csrf(Request, Controller))`
     *
     * Cada middleware decide si llama a `$next`. Si no lo hace, puede devolver
     * inmediatamente una redirección o un error y el controlador no se ejecuta.
     * Cuando todos permiten continuar, la petición llega a `$destination`, que
     * invoca el handler. La respuesta regresa después por las mismas capas en
     * orden inverso, permitiendo que un middleware también la modifique.
     *
     * @param callable|array{class-string, string} $handler
     * @param list<string> $parameters
     * @param list<MiddlewareInterface|callable> $middleware
     */
    private function runRoute(
        Request $request,
        callable|array $handler,
        array $parameters,
        array $middleware
    ): Response {
        // Esta closure es el destino final: ejecuta el controlador o callable
        // solamente después de que todos los middleware hayan continuado.
        $destination = fn (Request $nextRequest): Response => $this->invokeHandler(
            $handler,
            $nextRequest,
            $parameters
        );

        // array_reduce() envuelve la closure acumulada con cada middleware.
        // Se invierte la lista para conservar el orden declarado en la ruta:
        // el primer middleware registrado debe ser la capa más externa y, por
        // lo tanto, el primero que recibe la petición.
        $pipeline = array_reduce(
            array_reverse($middleware),
            function (Closure $next, MiddlewareInterface|callable $current): Closure {
                // La closure devuelta pasa a ser el nuevo acumulador. Conserva
                // tanto el middleware actual como la siguiente capa del flujo.
                return function (Request $currentRequest) use ($current, $next): Response {
                    if ($current instanceof MiddlewareInterface) {
                        return $current->process($currentRequest, $next);
                    }

                    // También se admiten callables con el mismo contrato:
                    // reciben Request y Closure, y deben devolver Response.
                    $response = $current($currentRequest, $next);

                    if (!$response instanceof Response) {
                        throw new LogicException('El middleware debe devolver una instancia de Response.');
                    }

                    return $response;
                };
            },
            $destination
        );

        // La composición anterior sólo construye closures. La ejecución real
        // comienza aquí, entregando la petición a la capa más externa.
        return $pipeline($request);
    }

    /**
     * Ejecuta un callable o instancia explícitamente el controlador configurado.
     *
     * @param callable|array{class-string, string} $handler
     * @param list<string> $parameters
     */
    private function invokeHandler(
        callable|array $handler,
        Request $request,
        array $parameters = []
    ): Response {
        if (is_array($handler) && is_string($handler[0])) {
            $controllerClass = $handler[0];

            if (!class_exists($controllerClass)) {
                throw new RuntimeException(
                    sprintf('No existe el controlador %s.', $controllerClass)
                );
            }

            $handler = [new $controllerClass(), $handler[1]];
        }

        if (!is_callable($handler)) {
            throw new RuntimeException('El controlador configurado no es ejecutable.');
        }

        $response = $handler($request, ...$parameters);

        if (!$response instanceof Response) {
            throw new LogicException('El controlador debe devolver una instancia de Response.');
        }

        return $response;
    }

    /**
     * Convierte una ruta declarativa en una expresión regular segura.
     */
    private function compilePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return '#^/$#';
        }

        $segments = explode('/', trim($path, '/'));
        $compiledSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $matches) === 1) {
                $compiledSegments[] = sprintf('(?P<%s>[^/]+)', $matches[1]);

                continue;
            }

            $compiledSegments[] = preg_quote($segment, '#');
        }

        return '#^/' . implode('/', $compiledSegments) . '/?$#';
    }

    /**
     * Obtiene los métodos registrados para informar una respuesta 405.
     */
    private function allowedMethodsFor(string $path): string
    {
        $methods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path) === 1) {
                $methods[] = $route['method'];
            }
        }

        return implode(', ', array_unique($methods));
    }
}
