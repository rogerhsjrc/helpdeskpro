<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    private ?View $view = null;

    /**
     * Renderiza una vista como respuesta HTML desde un controlador.
     *
     * @param array<string, mixed> $data
     */
    final protected function render(
        string $view,
        array $data = [],
        int $statusCode = 200,
        ?string $layout = 'layouts/app'
    ): Response {
        return Response::html(
            $this->view()->render($view, $data, $layout),
            $statusCode
        );
    }

    /**
     * Crea una respuesta de redirección para finalizar el flujo del controlador.
     */
    final protected function redirect(string $location, int $statusCode = 302): Response
    {
        return Response::redirect($location, $statusCode);
    }

    /**
     * Obtiene el renderizador compartido por las acciones del controlador.
     */
    private function view(): View
    {
        if ($this->view === null) {
            $this->view = new View(dirname(__DIR__) . '/Views');
        }

        return $this->view;
    }
}
