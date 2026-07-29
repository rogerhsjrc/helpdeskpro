<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    /**
     * Renderiza la portada que confirma el funcionamiento del núcleo HTTP.
     */
    public function index(Request $request): Response
    {
        return $this->render('home/index', [
            'title' => 'HelpDesk Pro',
        ]);
    }
}
