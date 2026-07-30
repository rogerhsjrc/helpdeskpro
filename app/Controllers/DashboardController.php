<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class DashboardController extends Controller
{
    /**
     * Renderiza la página protegida provisional de la fase de autenticación.
     */
    public function index(Request $request): Response
    {
        $authenticatedUser = Session::user();

        if ($authenticatedUser === null) {
            return $this->redirect('/login');
        }

        return $this->render('dashboard/index', [
            'title' => 'Dashboard | HelpDesk Pro',
            'usuario' => $authenticatedUser,
            'csrfToken' => Session::csrfToken(),
            'successMessage' => Session::pullFlash('success'),
        ]);
    }
}
