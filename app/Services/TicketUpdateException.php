<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

final class TicketUpdateException extends DomainException
{
    /**
     * Conserva el campo asociado y el estado HTTP apropiado para el rechazo.
     */
    public function __construct(
        private readonly string $field,
        private readonly int $statusCode,
        string $message
    ) {
        parent::__construct($message);
    }

    /**
     * Identifica el campo o regla que impidió actualizar el ticket.
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Devuelve el estado HTTP que debe representar el rechazo.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
