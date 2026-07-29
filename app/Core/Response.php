<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Response
{
    /**
     * Conserva el contenido, estado y headers de una respuesta HTTP.
     *
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $content = '',
        private readonly int $statusCode = 200,
        private readonly array $headers = []
    ) {
    }

    /**
     * Crea una respuesta HTML con codificación UTF-8.
     */
    public static function html(string $content, int $statusCode = 200): self
    {
        return new self(
            $content,
            $statusCode,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Serializa datos como una respuesta JSON UTF-8.
     *
     * @param array<string, mixed> $data
     *
     * @throws JsonException
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Crea una respuesta de redirección hacia una ubicación interna o externa.
     */
    public static function redirect(string $location, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $location]);
    }

    /**
     * Devuelve una copia de la respuesta con el header indicado.
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->content, $this->statusCode, $headers);
    }

    /**
     * Obtiene el contenido que será enviado al cliente.
     */
    public function content(): string
    {
        return $this->content;
    }

    /**
     * Obtiene el código de estado HTTP.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Obtiene los headers configurados para la respuesta.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Envía estado, headers y contenido utilizando las funciones nativas de PHP.
     */
    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->content;
    }
}
