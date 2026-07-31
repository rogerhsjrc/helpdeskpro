<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class ConfiguracionController extends Controller
{
    /**
     * Muestra el acceso central a las tablas maestras administrables.
     */
    public function index(Request $request): Response
    {
        return $this->render('configuraciones/index', [
            'title' => 'Configuraciones | HelpDesk Pro',
        ]);
    }
}
