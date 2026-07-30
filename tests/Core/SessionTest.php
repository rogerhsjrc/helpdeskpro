<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    /**
     * Restablece los datos globales para aislar cada escenario de sesión.
     */
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    /**
     * Elimina los datos utilizados por la prueba finalizada.
     */
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    /**
     * Conserva únicamente la identidad mínima del usuario autenticado.
     */
    public function testStoresAndReturnsAuthenticatedUser(): void
    {
        $usuario = [
            'id' => 12,
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'rol_id' => 2,
            'rol' => 'Técnico',
        ];

        Session::setUser($usuario);

        self::assertTrue(Session::authenticated());
        self::assertSame($usuario, Session::user());
    }

    /**
     * Elimina la identidad sin descartar otros datos asociados a la sesión.
     */
    public function testForgetsOnlyAuthenticatedUser(): void
    {
        Session::setUser([
            'id' => 3,
            'nombre' => 'Lucía',
            'apellido' => 'Gómez',
            'rol_id' => 1,
            'rol' => 'Administrador',
        ]);
        Session::flash('success', 'Sesión iniciada.');

        Session::forgetUser();

        self::assertFalse(Session::authenticated());
        self::assertNull(Session::user());
        self::assertSame('Sesión iniciada.', Session::pullFlash('success'));
    }

    /**
     * Rechaza una identidad incompleta aunque exista la clave de usuario.
     */
    public function testRejectsMalformedAuthenticatedUser(): void
    {
        $_SESSION['usuario'] = [
            'id' => 4,
            'nombre' => 'Usuario incompleto',
        ];

        self::assertFalse(Session::authenticated());
        self::assertNull(Session::user());
    }

    /**
     * Consume cada mensaje flash una sola vez.
     */
    public function testPullsFlashMessageOnlyOnce(): void
    {
        Session::flash('error', 'Credenciales inválidas.');

        self::assertSame(
            'Credenciales inválidas.',
            Session::pullFlash('error')
        );
        self::assertNull(Session::pullFlash('error'));
        self::assertArrayNotHasKey('_flash', $_SESSION);
    }

    /**
     * Genera un token CSRF seguro y lo reutiliza dentro de la misma sesión.
     */
    public function testCreatesAndReusesCsrfToken(): void
    {
        $token = Session::csrfToken();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, Session::csrfToken());
        self::assertTrue(Session::validateCsrfToken($token));
    }

    /**
     * Renueva el token CSRF e invalida el valor anterior.
     */
    public function testRegeneratesCsrfToken(): void
    {
        $previousToken = Session::csrfToken();
        $currentToken = Session::regenerateCsrfToken();

        self::assertNotSame($previousToken, $currentToken);
        self::assertFalse(Session::validateCsrfToken($previousToken));
        self::assertTrue(Session::validateCsrfToken($currentToken));
    }

    /**
     * Rechaza tokens ausentes o diferentes al asociado con la sesión.
     */
    public function testRejectsMissingOrInvalidCsrfToken(): void
    {
        Session::csrfToken();

        self::assertFalse(Session::validateCsrfToken(null));
        self::assertFalse(Session::validateCsrfToken(''));
        self::assertFalse(Session::validateCsrfToken(str_repeat('a', 64)));
    }
}
