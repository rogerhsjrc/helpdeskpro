<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Env
{
    /**
     * Carga variables desde un archivo sin sobrescribir el entorno del servidor.
     *
     * @throws RuntimeException Si el archivo o alguna definición no son válidos.
     */
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                sprintf('No se pudo leer el archivo de entorno: %s', $path)
            );
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException(
                sprintf('No se pudo cargar el archivo de entorno: %s', $path)
            );
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                throw new RuntimeException(
                    sprintf('Variable de entorno inválida en la línea %d.', $lineNumber + 1)
                );
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));

            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
                throw new RuntimeException(
                    sprintf('Nombre de variable de entorno inválido en la línea %d.', $lineNumber + 1)
                );
            }

            if (array_key_exists($name, $_ENV)) {
                continue;
            }

            $environmentValue = getenv($name);
            $value = $environmentValue === false
                ? self::normalizeValue($value)
                : $environmentValue;

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;

            if ($environmentValue === false) {
                putenv(sprintf('%s=%s', $name, $value));
            }
        }
    }

    /**
     * Retira las comillas exteriores de un valor cuando están balanceadas.
     */
    private static function normalizeValue(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $firstCharacter = $value[0];
        $lastCharacter = $value[strlen($value) - 1];

        if (
            ($firstCharacter === '"' && $lastCharacter === '"')
            || ($firstCharacter === "'" && $lastCharacter === "'")
        ) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Impide instanciar el cargador estático de variables de entorno.
     */
    private function __construct()
    {
    }
}
