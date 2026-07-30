<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends Controller
{
    private ?AuthService $authService;

    /**
     * Permite proporcionar el servicio de autenticación en escenarios controlados.
     */
    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService;
    }

    /**
     * Muestra el formulario de acceso y consume sus mensajes temporales.
     */
    public function showLogin(Request $request): Response
    {
        return $this->render('auth/login', [
            'title' => 'Iniciar sesión | HelpDesk Pro',
            'csrfToken' => Session::csrfToken(),
            'errorMessage' => Session::pullFlash('error'),
            'email' => Session::pullFlash('email', ''),
        ]);
    }

    /**
     * Valida las credenciales y crea una sesión autenticada segura.
     */
    public function login(Request $request): Response
    {
        $submittedEmail = $this->stringInput($request, 'email');
        $submittedPassword = $this->stringInput($request, 'password');
        $authenticatedUser = $this->authService()->authenticate(
            $submittedEmail,
            $submittedPassword
        );

        if ($authenticatedUser === null) {
            Session::flash(
                'error',
                'Las credenciales ingresadas no son válidas.'
            );
            Session::flash('email', substr(trim($submittedEmail), 0, 150));

            return $this->redirect('/login', 303);
        }

        Session::regenerateId();
        Session::setUser($authenticatedUser);
        Session::regenerateCsrfToken();
        Session::flash('success', 'Sesión iniciada correctamente.');

        return $this->redirect('/dashboard', 303);
    }

    /**
     * Destruye por completo la sesión autenticada.
     */
    public function logout(Request $request): Response
    {
        Session::destroy();

        return $this->redirect('/login', 303);
    }

    /**
     * Obtiene una entrada de formulario únicamente cuando es texto.
     */
    private function stringInput(Request $request, string $key): string
    {
        $submittedValue = $request->input($key);

        return is_string($submittedValue) ? $submittedValue : '';
    }

    /**
     * Obtiene el servicio de autenticación sin conectar la base en otras acciones.
     */
    private function authService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = new AuthService();
        }

        return $this->authService;
    }
}
