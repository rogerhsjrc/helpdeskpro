<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Categoria;
use App\Models\EstadoTicket;
use App\Models\Prioridad;
use App\Models\Ticket;
use App\Models\TicketHistorial;
use App\Models\Usuario;
use Closure;
use PDO;
use PDOException;
use Throwable;

final class TicketService
{
    private const MAX_CODE_ATTEMPTS = 5;

    private readonly PDO $databaseConnection;

    private readonly Categoria $categoryModel;

    private readonly Prioridad $priorityModel;

    private readonly EstadoTicket $ticketStatusModel;

    private readonly Ticket $ticketModel;

    private readonly TicketHistorial $ticketHistoryModel;

    private readonly Usuario $userModel;

    private readonly Closure $ticketCodeGenerator;

    /**
     * Comparte una conexión entre modelos para mantener alta y auditoría atómicas.
     *
     * @param (Closure(): string)|null $ticketCodeGenerator
     */
    public function __construct(
        ?PDO $databaseConnection = null,
        ?Closure $ticketCodeGenerator = null
    ) {
        $this->databaseConnection = $databaseConnection ?? Database::connection();
        $this->categoryModel = new Categoria($this->databaseConnection);
        $this->priorityModel = new Prioridad($this->databaseConnection);
        $this->ticketStatusModel = new EstadoTicket($this->databaseConnection);
        $this->ticketModel = new Ticket($this->databaseConnection);
        $this->ticketHistoryModel = new TicketHistorial($this->databaseConnection);
        $this->userModel = new Usuario($this->databaseConnection);
        $this->ticketCodeGenerator = $ticketCodeGenerator
            ?? static fn (): string => sprintf(
                'HD-%s-%s',
                date('Ymd'),
                strtoupper(bin2hex(random_bytes(3)))
            );
    }

    /**
     * Crea un ticket propio del cliente y audita la operación en una transacción.
     *
     * @throws TicketCreationException Si el rol o un catálogo no permite el alta.
     * @throws Throwable Si la persistencia no puede completarse de forma atómica.
     */
    public function createForClient(
        int $clientId,
        string $roleName,
        int $categoryId,
        int $priorityId,
        string $subject,
        string $description
    ): string {
        if ($clientId <= 0 || $roleName !== 'Cliente') {
            throw new TicketCreationException(
                'autorizacion',
                'Solamente un cliente puede crear un ticket propio.'
            );
        }

        $this->databaseConnection->beginTransaction();

        try {
            $category = $this->categoryModel->findById($categoryId);

            if ($category === null || !$category['activo']) {
                throw new TicketCreationException(
                    'categoria_id',
                    'La categoría seleccionada no está disponible.'
                );
            }

            $priority = $this->priorityModel->findById($priorityId);

            if ($priority === null || !$priority['activo']) {
                throw new TicketCreationException(
                    'prioridad_id',
                    'La prioridad seleccionada no está disponible.'
                );
            }

            $initialStatus = $this->ticketStatusModel->findByCode(
                EstadoTicket::CODIGO_ABIERTO
            );

            if ($initialStatus === null || !$initialStatus['activo']) {
                throw new TicketCreationException(
                    'estado',
                    'El estado inicial de tickets no está disponible.'
                );
            }

            [$ticketId, $ticketCode] = $this->insertWithUniqueCode(
                $clientId,
                $categoryId,
                $priorityId,
                $initialStatus['id'],
                $subject,
                $description
            );
            $this->ticketHistoryModel->recordCreation(
                $ticketId,
                $clientId,
                $ticketCode
            );
            $this->databaseConnection->commit();

            return $ticketCode;
        } catch (Throwable $exception) {
            if ($this->databaseConnection->inTransaction()) {
                $this->databaseConnection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Obtiene un ticket editable o informa por qué la identidad no puede editarlo.
     *
     * @return array<string, mixed>|null
     *
     * @throws TicketUpdateException Si el ticket es visible pero no editable.
     */
    public function findEditableOriginal(
        string $ticketCode,
        int $userId,
        string $roleName
    ): ?array {
        $ticket = $this->ticketModel->findVisibleByCode(
            $ticketCode,
            $userId,
            $roleName
        );

        if ($ticket === null) {
            return null;
        }

        $this->assertCanEditOriginal($ticket, $userId, $roleName);

        return $ticket;
    }

    /**
     * Actualiza y audita sólo los campos originales que cambiaron.
     *
     * @throws TicketUpdateException Si el recurso o la categoría no son válidos.
     * @throws Throwable Si la transacción no puede completarse.
     */
    public function updateOriginal(
        string $ticketCode,
        int $userId,
        string $roleName,
        int $categoryId,
        string $subject,
        string $description
    ): bool {
        $this->databaseConnection->beginTransaction();

        try {
            $ticket = $this->ticketModel->findVisibleByCodeForUpdate(
                $ticketCode,
                $userId,
                $roleName
            );

            if ($ticket === null) {
                throw new TicketUpdateException(
                    'ticket',
                    404,
                    'El ticket solicitado no existe.'
                );
            }

            $this->assertCanEditOriginal($ticket, $userId, $roleName);
            $category = $this->categoryModel->findById($categoryId);
            $categoryChanged = $ticket['categoria']['id'] !== $categoryId;

            if (
                $category === null
                || ($categoryChanged && !$category['activo'])
            ) {
                throw new TicketUpdateException(
                    'categoria_id',
                    422,
                    'La categoría seleccionada no está disponible.'
                );
            }

            $changes = [
                'categoria' => [
                    $ticket['categoria']['nombre'],
                    $category['nombre'],
                    $categoryChanged,
                ],
                'asunto' => [
                    $ticket['asunto'],
                    $subject,
                    $ticket['asunto'] !== $subject,
                ],
                'descripcion' => [
                    $ticket['descripcion'],
                    $description,
                    $ticket['descripcion'] !== $description,
                ],
            ];
            $hasChanges = false;

            foreach ($changes as $change) {
                if ($change[2]) {
                    $hasChanges = true;

                    break;
                }
            }

            if ($hasChanges) {
                $this->ticketModel->updateOriginal(
                    $ticket['id'],
                    $categoryId,
                    $subject,
                    $description
                );

                foreach ($changes as $fieldName => $change) {
                    if (!$change[2]) {
                        continue;
                    }

                    $this->ticketHistoryModel->recordFieldChange(
                        $ticket['id'],
                        $userId,
                        $fieldName,
                        (string) $change[0],
                        (string) $change[1]
                    );
                }
            }

            $this->databaseConnection->commit();

            return $hasChanges;
        } catch (Throwable $exception) {
            if ($this->databaseConnection->inTransaction()) {
                $this->databaseConnection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Obtiene un ticket administrable para mostrar su asignación actual.
     *
     * @return array<string, mixed>|null
     *
     * @throws TicketUpdateException Si el rol no administra asignaciones.
     */
    public function findAssignable(
        string $ticketCode,
        int $userId,
        string $roleName
    ): ?array {
        $this->assertAdministrator($roleName);

        return $this->ticketModel->findVisibleByCode(
            $ticketCode,
            $userId,
            $roleName
        );
    }

    /**
     * Asigna un técnico activo y audita la primera asignación o reasignación.
     *
     * @throws TicketUpdateException Si el recurso, rol o técnico no son válidos.
     * @throws Throwable Si la operación transaccional falla.
     */
    public function assignTechnician(
        string $ticketCode,
        int $administratorId,
        string $roleName,
        int $technicianId
    ): bool {
        $this->assertAdministrator($roleName);
        $this->databaseConnection->beginTransaction();

        try {
            $ticket = $this->ticketModel->findVisibleByCodeForUpdate(
                $ticketCode,
                $administratorId,
                $roleName
            );

            if ($ticket === null) {
                throw new TicketUpdateException(
                    'ticket',
                    404,
                    'El ticket solicitado no existe.'
                );
            }

            $technician = $this->userModel->findActiveTechnicianById($technicianId);

            if ($technician === null) {
                throw new TicketUpdateException(
                    'tecnico_id',
                    422,
                    'El técnico seleccionado no está disponible.'
                );
            }

            if (($ticket['tecnico']['id'] ?? null) === $technicianId) {
                $this->databaseConnection->commit();

                return false;
            }

            $previousTechnician = $ticket['tecnico'] === null
                ? null
                : trim($ticket['tecnico']['nombre'] . ' ' . $ticket['tecnico']['apellido']);
            $newTechnician = trim($technician['nombre'] . ' ' . $technician['apellido']);
            $this->ticketModel->updateTechnician($ticket['id'], $technicianId);
            $this->ticketHistoryModel->recordTechnicianChange(
                $ticket['id'],
                $administratorId,
                $previousTechnician,
                $newTechnician
            );
            $this->databaseConnection->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->databaseConnection->inTransaction()) {
                $this->databaseConnection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Obtiene un ticket gestionable por administrador o técnico asignado.
     *
     * @return array<string, mixed>|null
     */
    public function findManageable(
        string $ticketCode,
        int $userId,
        string $roleName
    ): ?array {
        $this->assertWorkflowRole($roleName);

        return $this->ticketModel->findVisibleByCode(
            $ticketCode,
            $userId,
            $roleName
        );
    }

    /**
     * Cambia estado y prioridad, aplica fechas y audita en una transacción.
     */
    public function updateWorkflow(
        string $ticketCode,
        int $userId,
        string $roleName,
        int $ticketStatusId,
        int $priorityId
    ): bool {
        $this->assertWorkflowRole($roleName);
        $this->databaseConnection->beginTransaction();

        try {
            $ticket = $this->ticketModel->findVisibleByCodeForUpdate(
                $ticketCode,
                $userId,
                $roleName
            );

            if ($ticket === null) {
                throw new TicketUpdateException('ticket', 404, 'El ticket solicitado no existe.');
            }

            $this->assertCanManageWorkflow($ticket, $userId, $roleName);
            $targetStatus = $this->ticketStatusModel->findById($ticketStatusId);
            $targetPriority = $this->priorityModel->findById($priorityId);
            $statusChanged = $ticket['estado']['id'] !== $ticketStatusId;
            $priorityChanged = $ticket['prioridad']['id'] !== $priorityId;

            if ($targetStatus === null || ($statusChanged && !$targetStatus['activo'])) {
                throw new TicketUpdateException(
                    'estado_id',
                    422,
                    'El estado seleccionado no está disponible.'
                );
            }

            if ($targetPriority === null || ($priorityChanged && !$targetPriority['activo'])) {
                throw new TicketUpdateException(
                    'prioridad_id',
                    422,
                    'La prioridad seleccionada no está disponible.'
                );
            }

            if ($statusChanged) {
                $this->assertAllowedTransition(
                    $ticket['estado']['codigo'],
                    $targetStatus['codigo'],
                    $ticket['tecnico'] !== null
                );
                $this->ticketModel->updateStatus(
                    $ticket['id'],
                    $targetStatus['id'],
                    $targetStatus['codigo']
                );
                $this->ticketHistoryModel->recordWorkflowChange(
                    $ticket['id'],
                    $userId,
                    TicketHistorial::TIPO_CAMBIO_ESTADO,
                    'estado',
                    $ticket['estado']['nombre'],
                    $targetStatus['nombre']
                );
            }

            if ($priorityChanged) {
                $this->ticketModel->updatePriority($ticket['id'], $targetPriority['id']);
                $this->ticketHistoryModel->recordWorkflowChange(
                    $ticket['id'],
                    $userId,
                    TicketHistorial::TIPO_CAMBIO_PRIORIDAD,
                    'prioridad',
                    $ticket['prioridad']['nombre'],
                    $targetPriority['nombre']
                );
            }

            $this->databaseConnection->commit();

            return $statusChanged || $priorityChanged;
        } catch (Throwable $exception) {
            if ($this->databaseConnection->inTransaction()) {
                $this->databaseConnection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Restringe la gestión del flujo al administrador o técnico.
     */
    private function assertWorkflowRole(string $roleName): void
    {
        if (!in_array($roleName, ['Administrador', 'Técnico'], true)) {
            throw new TicketUpdateException(
                'autorizacion',
                403,
                'El usuario no puede cambiar estado o prioridad.'
            );
        }
    }

    /**
     * Confirma que el técnico sea el asignado; el administrador gestiona todos.
     *
     * @param array<string, mixed> $ticket
     */
    private function assertCanManageWorkflow(
        array $ticket,
        int $userId,
        string $roleName
    ): void {
        if ($roleName === 'Administrador') {
            return;
        }

        if ($ticket['tecnico'] === null || $ticket['tecnico']['id'] !== $userId) {
            throw new TicketUpdateException(
                'autorizacion',
                403,
                'Sólo el técnico asignado puede gestionar este ticket.'
            );
        }
    }

    /**
     * Valida la matriz de transición y la asignación requerida por estados operativos.
     */
    private function assertAllowedTransition(
        string $currentCode,
        string $targetCode,
        bool $hasTechnician
    ): void {
        $allowedTransitions = [
            EstadoTicket::CODIGO_ABIERTO => [
                EstadoTicket::CODIGO_ASIGNADO,
                EstadoTicket::CODIGO_CANCELADO,
            ],
            EstadoTicket::CODIGO_ASIGNADO => [
                EstadoTicket::CODIGO_EN_PROCESO,
                EstadoTicket::CODIGO_CANCELADO,
            ],
            EstadoTicket::CODIGO_EN_PROCESO => [
                EstadoTicket::CODIGO_PENDIENTE_CLIENTE,
                EstadoTicket::CODIGO_RESUELTO,
                EstadoTicket::CODIGO_CANCELADO,
            ],
            EstadoTicket::CODIGO_PENDIENTE_CLIENTE => [
                EstadoTicket::CODIGO_EN_PROCESO,
                EstadoTicket::CODIGO_RESUELTO,
                EstadoTicket::CODIGO_CANCELADO,
            ],
            EstadoTicket::CODIGO_RESUELTO => [
                EstadoTicket::CODIGO_CERRADO,
                EstadoTicket::CODIGO_EN_PROCESO,
            ],
            EstadoTicket::CODIGO_CERRADO => [EstadoTicket::CODIGO_EN_PROCESO],
            EstadoTicket::CODIGO_CANCELADO => [EstadoTicket::CODIGO_ABIERTO],
        ];

        if (!in_array($targetCode, $allowedTransitions[$currentCode] ?? [], true)) {
            throw new TicketUpdateException(
                'estado_id',
                422,
                'La transición de estado seleccionada no está permitida.'
            );
        }

        $statesRequiringTechnician = [
            EstadoTicket::CODIGO_ASIGNADO,
            EstadoTicket::CODIGO_EN_PROCESO,
            EstadoTicket::CODIGO_PENDIENTE_CLIENTE,
            EstadoTicket::CODIGO_RESUELTO,
        ];

        if (!$hasTechnician && in_array($targetCode, $statesRequiringTechnician, true)) {
            throw new TicketUpdateException(
                'estado_id',
                422,
                'Debe asignar un técnico antes de utilizar ese estado.'
            );
        }
    }

    /**
     * Restringe las asignaciones al rol administrador también en el servicio.
     *
     * @throws TicketUpdateException Si el rol no está autorizado.
     */
    private function assertAdministrator(string $roleName): void
    {
        if ($roleName !== 'Administrador') {
            throw new TicketUpdateException(
                'autorizacion',
                403,
                'Solamente un administrador puede asignar técnicos.'
            );
        }
    }

    /**
     * Aplica la regla de edición para administrador o cliente propietario abierto.
     *
     * @param array<string, mixed> $ticket
     *
     * @throws TicketUpdateException Si el estado, asignación o rol impiden editar.
     */
    private function assertCanEditOriginal(
        array $ticket,
        int $userId,
        string $roleName
    ): void {
        if ($roleName === 'Administrador') {
            return;
        }

        $clientCanEdit = $roleName === 'Cliente'
            && $ticket['cliente']['id'] === $userId
            && $ticket['estado']['codigo'] === EstadoTicket::CODIGO_ABIERTO
            && $ticket['tecnico'] === null;

        if (!$clientCanEdit) {
            throw new TicketUpdateException(
                'autorizacion',
                403,
                'El contenido original de este ticket ya no puede editarse.'
            );
        }
    }

    /**
     * Reintenta el INSERT sólo ante colisiones del código público generado.
     *
     * @return array{0: int, 1: string}
     *
     * @throws PDOException Si se agotan los intentos o falla otra restricción.
     */
    private function insertWithUniqueCode(
        int $clientId,
        int $categoryId,
        int $priorityId,
        int $ticketStatusId,
        string $subject,
        string $description
    ): array {
        $lastCollision = null;

        for ($attempt = 1; $attempt <= self::MAX_CODE_ATTEMPTS; $attempt++) {
            $ticketCode = ($this->ticketCodeGenerator)();

            try {
                $ticketId = $this->ticketModel->create(
                    $ticketCode,
                    $clientId,
                    $categoryId,
                    $priorityId,
                    $ticketStatusId,
                    $subject,
                    $description
                );

                return [$ticketId, $ticketCode];
            } catch (PDOException $exception) {
                if (!$this->isDuplicateCode($exception)) {
                    throw $exception;
                }

                $lastCollision = $exception;
            }
        }

        throw $lastCollision ?? new PDOException(
            'No se pudo generar un código único para el ticket.'
        );
    }

    /**
     * Identifica la colisión de la restricción única del código de tickets.
     */
    private function isDuplicateCode(PDOException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        $driverMessage = $exception->errorInfo[2] ?? '';

        return (string) $exception->getCode() === '23000'
            && (int) $driverCode === 1062
            && is_string($driverMessage)
            && str_contains($driverMessage, 'uq_tickets_codigo');
    }
}
