<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class ErrorController extends Controller
{
    /**
     * Renderiza la respuesta visual para una ruta inexistente.
     */
    public function notFound(Request $request): Response
    {
        return $this->render(
            'errors/404',
            ['title' => 'Página no encontrada'],
            404
        );
    }
}
