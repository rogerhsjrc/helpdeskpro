<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class WebRoutesTest extends TestCase
{
    /**
     * Comprueba el flujo integrado de la ruta principal hasta la vista.
     */
    public function testHomeRouteRendersApplicationView(): void
    {
        $router = $this->loadRouter();

        $response = $router->dispatch(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('<!DOCTYPE html>', $response->content());
        self::assertStringContainsString('núcleo HTTP', $response->content());
    }

    /**
     * Comprueba el flujo integrado de una ruta inexistente.
     */
    public function testUnknownRouteRendersApplicationNotFoundView(): void
    {
        $router = $this->loadRouter();

        $response = $router->dispatch(new Request('GET', '/ruta-inexistente'));

        self::assertSame(404, $response->statusCode());
        self::assertStringContainsString(
            'La página solicitada no existe.',
            $response->content()
        );
    }

    /**
     * Carga las rutas web reales en una instancia aislada.
     */
    private function loadRouter(): Router
    {
        $router = new Router();

        require dirname(__DIR__, 2) . '/routes/web.php';

        return $router;
    }
}
