<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class TicketHistorial
{
    public const string TIPO_CREACION = 'CREACION';

    public const string TIPO_EDICION = 'EDICION';

    public const string TIPO_ASIGNACION = 'ASIGNACION';

    public const string TIPO_CAMBIO_TECNICO = 'CAMBIO_TECNICO';

    public const string TIPO_CAMBIO_ESTADO = 'CAMBIO_ESTADO';

    public const string TIPO_CAMBIO_PRIORIDAD = 'CAMBIO_PRIORIDAD';

    private readonly PDO $databaseConnection;

    /**
     * Recibe una conexión controlada o utiliza la conexión compartida de la aplicación.
     */
    public function __construct(?PDO $databaseConnection = null)
    {
        $this->databaseConnection = $databaseConnection ?? Database::connection();
    }

    /**
     * Registra el evento inicial que permite auditar quién creó el ticket.
     */
    public function recordCreation(
        int $ticketId,
        int $userId,
        string $ticketCode
    ): void {
        $createHistoryStatement = $this->databaseConnection->prepare(
            'INSERT INTO ticket_historial (
                ticket_id,
                usuario_id,
                tipo_evento,
                valor_nuevo,
                descripcion
             ) VALUES (
                :ticket_id,
                :usuario_id,
                :tipo_evento,
                :valor_nuevo,
                :descripcion
             )'
        );
        $createHistoryStatement->execute([
            'ticket_id' => $ticketId,
            'usuario_id' => $userId,
            'tipo_evento' => self::TIPO_CREACION,
            'valor_nuevo' => $ticketCode,
            'descripcion' => 'Ticket creado por el cliente.',
        ]);
    }

    /**
     * Audita un campo modificado conservando sus valores anterior y nuevo.
     */
    public function recordFieldChange(
        int $ticketId,
        int $userId,
        string $fieldName,
        string $previousValue,
        string $newValue
    ): void {
        $createHistoryStatement = $this->databaseConnection->prepare(
            'INSERT INTO ticket_historial (
                ticket_id,
                usuario_id,
                tipo_evento,
                campo_modificado,
                valor_anterior,
                valor_nuevo,
                descripcion
             ) VALUES (
                :ticket_id,
                :usuario_id,
                :tipo_evento,
                :campo_modificado,
                :valor_anterior,
                :valor_nuevo,
                :descripcion
             )'
        );
        $createHistoryStatement->execute([
            'ticket_id' => $ticketId,
            'usuario_id' => $userId,
            'tipo_evento' => self::TIPO_EDICION,
            'campo_modificado' => $fieldName,
            'valor_anterior' => $previousValue,
            'valor_nuevo' => $newValue,
            'descripcion' => sprintf('Se modificó el campo %s.', $fieldName),
        ]);
    }

    /**
     * Audita una primera asignación o una reasignación de técnico.
     */
    public function recordTechnicianChange(
        int $ticketId,
        int $userId,
        ?string $previousTechnician,
        string $newTechnician
    ): void {
        $eventType = $previousTechnician === null
            ? self::TIPO_ASIGNACION
            : self::TIPO_CAMBIO_TECNICO;
        $createHistoryStatement = $this->databaseConnection->prepare(
            'INSERT INTO ticket_historial (
                ticket_id,
                usuario_id,
                tipo_evento,
                campo_modificado,
                valor_anterior,
                valor_nuevo,
                descripcion
             ) VALUES (
                :ticket_id,
                :usuario_id,
                :tipo_evento,
                :campo_modificado,
                :valor_anterior,
                :valor_nuevo,
                :descripcion
             )'
        );
        $createHistoryStatement->execute([
            'ticket_id' => $ticketId,
            'usuario_id' => $userId,
            'tipo_evento' => $eventType,
            'campo_modificado' => 'tecnico',
            'valor_anterior' => $previousTechnician,
            'valor_nuevo' => $newTechnician,
            'descripcion' => $previousTechnician === null
                ? 'Se asignó un técnico al ticket.'
                : 'Se reasignó el técnico del ticket.',
        ]);
    }

    /**
     * Audita un cambio de estado o prioridad con valores legibles.
     */
    public function recordWorkflowChange(
        int $ticketId,
        int $userId,
        string $eventType,
        string $fieldName,
        string $previousValue,
        string $newValue
    ): void {
        $allowedEventTypes = [
            self::TIPO_CAMBIO_ESTADO,
            self::TIPO_CAMBIO_PRIORIDAD,
        ];

        if (!in_array($eventType, $allowedEventTypes, true)) {
            throw new \InvalidArgumentException('El tipo de evento no es válido.');
        }

        $statement = $this->databaseConnection->prepare(
            'INSERT INTO ticket_historial (
                ticket_id, usuario_id, tipo_evento, campo_modificado,
                valor_anterior, valor_nuevo, descripcion
             ) VALUES (
                :ticket_id, :usuario_id, :tipo_evento, :campo_modificado,
                :valor_anterior, :valor_nuevo, :descripcion
             )'
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'usuario_id' => $userId,
            'tipo_evento' => $eventType,
            'campo_modificado' => $fieldName,
            'valor_anterior' => $previousValue,
            'valor_nuevo' => $newValue,
            'descripcion' => sprintf('Se modificó %s del ticket.', $fieldName),
        ]);
    }
}
