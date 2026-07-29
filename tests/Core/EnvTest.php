<?php

declare(strict_types=1);

namespace Tests\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    private ?string $environmentFile = null;

    /**
     * @var list<string>
     */
    private array $environmentVariables = [];

    /**
     * Elimina archivos y variables temporales después de cada prueba.
     */
    protected function tearDown(): void
    {
        if ($this->environmentFile !== null && is_file($this->environmentFile)) {
            unlink($this->environmentFile);
        }

        foreach ($this->environmentVariables as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    /**
     * Comprueba la carga de valores simples, comentados y entre comillas.
     */
    public function testLoadsValuesFromReadableFile(): void
    {
        $plainName = $this->trackVariable('HELPDESK_PHPUNIT_PLAIN');
        $quotedName = $this->trackVariable('HELPDESK_PHPUNIT_QUOTED');
        $this->writeEnvironmentFile(
            "# Comentario\n"
            . "{$plainName}=valor\n"
            . "{$quotedName}=\"valor con espacios\"\n"
        );

        Env::load($this->environmentFile);

        self::assertSame('valor', $_ENV[$plainName]);
        self::assertSame('valor con espacios', $_ENV[$quotedName]);
        self::assertSame('valor', $_SERVER[$plainName]);
        self::assertSame('valor', getenv($plainName));
    }

    /**
     * Comprueba que el servidor tenga prioridad sobre el archivo local.
     */
    public function testRespectsValueAlreadyDefinedByServer(): void
    {
        $name = $this->trackVariable('HELPDESK_PHPUNIT_EXISTING');
        putenv("{$name}=desde_servidor");
        $this->writeEnvironmentFile("{$name}=desde_archivo\n");

        Env::load($this->environmentFile);

        self::assertSame('desde_servidor', $_ENV[$name]);
        self::assertSame('desde_servidor', $_SERVER[$name]);
    }

    /**
     * Comprueba el rechazo de una ruta de entorno inexistente.
     */
    public function testThrowsExceptionWhenFileDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo leer el archivo de entorno');

        Env::load(sys_get_temp_dir() . '/helpdesk_env_inexistente_' . uniqid());
    }

    /**
     * Comprueba el rechazo de líneas que no asignan un valor.
     */
    public function testThrowsExceptionForLineWithoutAssignment(): void
    {
        $this->writeEnvironmentFile("VARIABLE_SIN_VALOR\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Variable de entorno inválida en la línea 1.');

        Env::load($this->environmentFile);
    }

    /**
     * Comprueba el rechazo de nombres de variable fuera de la convención.
     */
    public function testThrowsExceptionForInvalidVariableName(): void
    {
        $this->writeEnvironmentFile("variable-minuscula=valor\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nombre de variable de entorno inválido');

        Env::load($this->environmentFile);
    }

    /**
     * Registra una variable temporal para restaurar el entorno al finalizar.
     */
    private function trackVariable(string $name): string
    {
        $this->environmentVariables[] = $name;
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);

        return $name;
    }

    /**
     * Crea el archivo temporal utilizado por el escenario actual.
     */
    private function writeEnvironmentFile(string $content): void
    {
        $file = tempnam(sys_get_temp_dir(), 'helpdesk_env_');

        if ($file === false) {
            self::fail('No se pudo crear el archivo temporal de entorno.');
        }

        $this->environmentFile = $file;

        self::assertNotFalse(file_put_contents($file, $content));
    }
}
