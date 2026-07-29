<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewTest extends TestCase
{
    private View $view;

    /**
     * Crea un renderizador apuntando a las vistas reales de la aplicación.
     */
    protected function setUp(): void
    {
        $this->view = new View(dirname(__DIR__, 2) . '/app/Views');
    }

    /**
     * Comprueba la composición de una vista dentro del layout.
     */
    public function testRendersViewInsideLayout(): void
    {
        $content = $this->view->render(
            'home/index',
            ['title' => 'Portada de prueba']
        );

        self::assertStringContainsString('<!DOCTYPE html>', $content);
        self::assertStringContainsString('<title>Portada de prueba</title>', $content);
        self::assertStringContainsString('núcleo HTTP', $content);
    }

    /**
     * Comprueba el renderizado directo cuando no se solicita layout.
     */
    public function testRendersViewWithoutLayout(): void
    {
        $content = $this->view->render('home/index', layout: null);

        self::assertStringNotContainsString('<!DOCTYPE html>', $content);
        self::assertStringContainsString('<h1>HelpDesk Pro</h1>', $content);
    }

    /**
     * Comprueba el escape de contenido potencialmente ejecutable.
     */
    public function testEscapesDynamicContent(): void
    {
        self::assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            View::escape("<script>alert('x')</script>")
        );
    }

    /**
     * Comprueba el rechazo de rutas que intentan salir del directorio de vistas.
     */
    public function testRejectsInvalidViewName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El nombre de la vista no es válido.');

        $this->view->render('../.env');
    }

    /**
     * Comprueba el error explícito ante una vista inexistente.
     */
    public function testRejectsMissingView(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No existe la vista');

        $this->view->render('missing/view');
    }
}
