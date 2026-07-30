<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Session
{
    private const USER_KEY = 'usuario';

    private const FLASH_KEY = '_flash';

    private const CSRF_TOKEN_KEY = '_csrf_token';

    /**
     * Guarda la identidad mínima del usuario autenticado.
     *
     * @param array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     rol_id: int,
     *     rol: string
     * } $usuario
     */
    public static function setUser(array $usuario): void
    {
        $_SESSION[self::USER_KEY] = $usuario;
    }

    /**
     * Obtiene la identidad del usuario autenticado, cuando existe.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     rol_id: int,
     *     rol: string
     * }|null
     */
    public static function user(): ?array
    {
        $usuario = $_SESSION[self::USER_KEY] ?? null;

        if (
            !is_array($usuario)
            || !is_int($usuario['id'] ?? null)
            || !is_string($usuario['nombre'] ?? null)
            || !is_string($usuario['apellido'] ?? null)
            || !is_int($usuario['rol_id'] ?? null)
            || !is_string($usuario['rol'] ?? null)
        ) {
            return null;
        }

        return [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'rol_id' => $usuario['rol_id'],
            'rol' => $usuario['rol'],
        ];
    }

    /**
     * Indica si la sesión contiene una identidad autenticada válida.
     */
    public static function authenticated(): bool
    {
        return self::user() !== null;
    }

    /**
     * Elimina la identidad autenticada sin afectar otros datos de sesión.
     */
    public static function forgetUser(): void
    {
        unset($_SESSION[self::USER_KEY]);
    }

    /**
     * Regenera el identificador activo y elimina el archivo de sesión anterior.
     *
     * @throws RuntimeException Si la sesión no está activa o no puede regenerarse.
     */
    public static function regenerateId(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'No se puede regenerar una sesión que no está activa.'
            );
        }

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'No se pudo regenerar el identificador de sesión.'
            );
        }
    }

    /**
     * Vacía los datos, elimina la cookie y destruye la sesión activa.
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (filter_var(ini_get('session.use_cookies'), FILTER_VALIDATE_BOOL)) {
            $cookieParameters = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookieParameters['path'],
                'domain' => $cookieParameters['domain'],
                'secure' => $cookieParameters['secure'],
                'httponly' => $cookieParameters['httponly'],
                'samesite' => $cookieParameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Guarda un mensaje temporal que podrá consumirse una sola vez.
     */
    public static function flash(string $key, string $message): void
    {
        $_SESSION[self::FLASH_KEY][$key] = $message;
    }

    /**
     * Consume un mensaje temporal o devuelve el valor alternativo indicado.
     */
    public static function pullFlash(string $key, ?string $default = null): ?string
    {
        $message = $_SESSION[self::FLASH_KEY][$key] ?? $default;

        unset($_SESSION[self::FLASH_KEY][$key]);

        if (isset($_SESSION[self::FLASH_KEY]) && $_SESSION[self::FLASH_KEY] === []) {
            unset($_SESSION[self::FLASH_KEY]);
        }

        return is_string($message) ? $message : $default;
    }

    /**
     * Obtiene o crea el token CSRF asociado con la sesión actual.
     */
    public static function csrfToken(): string
    {
        $token = $_SESSION[self::CSRF_TOKEN_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = self::regenerateCsrfToken();
        }

        return $token;
    }

    /**
     * Reemplaza el token CSRF actual por un valor criptográficamente seguro.
     */
    public static function regenerateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;

        return $token;
    }

    /**
     * Compara un token recibido con el token asociado a la sesión.
     */
    public static function validateCsrfToken(?string $submittedToken): bool
    {
        $sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? null;

        if (
            !is_string($sessionToken)
            || $sessionToken === ''
            || !is_string($submittedToken)
            || $submittedToken === ''
        ) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Impide instanciar el administrador estático de la sesión actual.
     */
    private function __construct()
    {
    }
}
