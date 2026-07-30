<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;

final class AuthService
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$XHCj1dNq6bKQnplK7EKVH.t1k83Vt.7jqfVbwS.16u6vcRBZGQ89W';

    private readonly Usuario $usuarioModel;

    /**
     * Recibe el modelo utilizado para consultar y actualizar usuarios.
     */
    public function __construct(?Usuario $usuarioModel = null)
    {
        $this->usuarioModel = $usuarioModel ?? new Usuario();
    }

    /**
     * Valida credenciales activas y devuelve la identidad mínima para la sesión.
     *
     * @return array{
     *     id: int,
     *     nombre: string,
     *     apellido: string,
     *     rol_id: int,
     *     rol: string
     * }|null
     */
    public function authenticate(string $email, string $password): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        if (
            $normalizedEmail === ''
            || strlen($normalizedEmail) > 150
            || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false
            || $password === ''
        ) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);

            return null;
        }

        $usuario = $this->usuarioModel->findActiveByEmail($normalizedEmail);
        $passwordHash = $usuario['password'] ?? self::DUMMY_PASSWORD_HASH;
        $validPassword = password_verify($password, $passwordHash);

        if ($usuario === null || !$validPassword) {
            return null;
        }

        $this->usuarioModel->updateLastAccess($usuario['id']);

        return [
            'id' => $usuario['id'],
            'nombre' => $usuario['nombre'],
            'apellido' => $usuario['apellido'],
            'rol_id' => $usuario['rol_id'],
            'rol' => $usuario['rol'],
        ];
    }
}
