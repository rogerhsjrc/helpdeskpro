<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    /**
     * Define el directorio raíz desde el que pueden resolverse las vistas.
     */
    public function __construct(
        private readonly string $basePath
    ) {
    }

    /**
     * Renderiza una vista y, opcionalmente, la inserta dentro de un layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(
        string $view,
        array $data = [],
        ?string $layout = 'layouts/app'
    ): string {
        $content = $this->renderFile($view, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, [...$data, 'content' => $content]);
    }

    /**
     * Escapa contenido dinámico para su presentación segura en HTML.
     */
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Ejecuta un archivo de vista aislando los datos recibidos.
     *
     * @param array<string, mixed> $data
     *
     * @throws RuntimeException Si el nombre o archivo de vista no son válidos.
     */
    private function renderFile(string $view, array $data): string
    {
        if (preg_match('#^[a-zA-Z0-9/_-]+$#', $view) !== 1) {
            throw new RuntimeException('El nombre de la vista no es válido.');
        }

        $file = rtrim($this->basePath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $view)
            . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(
                sprintf('No existe la vista %s.', $view)
            );
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;
        } catch (Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }

        $rendered = ob_get_clean();

        if ($rendered === false) {
            throw new RuntimeException(
                sprintf('No se pudo renderizar la vista %s.', $view)
            );
        }

        return $rendered;
    }
}
