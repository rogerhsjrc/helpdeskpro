<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Controller;
use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    /**
     * Comprueba que un controlador transforme una vista en respuesta HTML.
     */
    public function testRendersViewAsHtmlResponse(): void
    {
        $controller = new class extends Controller {
            /**
             * Expone el renderizado protegido para el escenario de prueba.
             */
            public function home(): Response
            {
                return $this->render(
                    'home/index',
                    ['title' => 'Controlador de prueba'],
                    201
                );
            }
        };

        $response = $controller->home();

        self::assertSame(201, $response->statusCode());
        self::assertStringContainsString('Controlador de prueba', $response->content());
    }

    /**
     * Comprueba la creación de redirecciones desde un controlador.
     */
    public function testCreatesRedirectResponse(): void
    {
        $controller = new class extends Controller {
            /**
             * Expone la redirección protegida para el escenario de prueba.
             */
            public function goToLogin(): Response
            {
                return $this->redirect('/login', 303);
            }
        };

        $response = $controller->goToLogin();

        self::assertSame(303, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }
}
