<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * Conserva una representación inmutable de los datos de la petición.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
        private readonly array $server = []
    ) {
    }

    /**
     * Construye la petición actual a partir de las superglobales de PHP.
     */
    public static function capture(): self
    {
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER
        );
    }

    /**
     * Obtiene el método HTTP normalizado en mayúsculas.
     */
    public function method(): string
    {
        return strtoupper($this->method);
    }

    /**
     * Obtiene la ruta normalizada sin query string ni barra final innecesaria.
     */
    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Obtiene la URI original recibida por la aplicación.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Obtiene un parámetro de query o la colección completa.
     *
     * @return array<string, mixed>|mixed
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * Obtiene un dato del cuerpo o la colección completa del formulario.
     *
     * @return array<string, mixed>|mixed
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * Obtiene un archivo cargado o la colección completa de archivos.
     *
     * @return array<string, mixed>|mixed
     */
    public function file(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }

        return $this->files[$key] ?? null;
    }

    /**
     * Obtiene un header HTTP por su nombre sin depender del formato interno.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        $normalizedName = strtoupper(str_replace('-', '_', $name));
        $serverKey = in_array($normalizedName, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
            ? $normalizedName
            : 'HTTP_' . $normalizedName;
        $value = $this->server[$serverKey] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Indica si la petición utiliza el método HTTP esperado.
     */
    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }
}
