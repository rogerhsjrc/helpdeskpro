<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

final class TicketCreationException extends DomainException
{
    /**
     * Conserva el campo de formulario asociado con la regla de negocio fallida.
     */
    public function __construct(
        private readonly string $field,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * Identifica el campo que debe mostrar el mensaje al usuario.
     */
    public function field(): string
    {
        return $this->field;
    }
}
