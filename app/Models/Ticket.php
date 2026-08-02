<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use PDO;

final class Ticket
{
    private readonly PDO $databaseConnection;

    /**
     * Recibe una conexión controlada o utiliza la conexión compartida de la aplicación.
     */
    public function __construct(?PDO $databaseConnection = null)
    {
        $this->databaseConnection = $databaseConnection ?? Database::connection();
    }

    /**
     * Pagina únicamente los tickets visibles para la identidad y el rol indicados.
     *
     * @return array{
     *     tickets: list<array<string, mixed>>,
     *     total: int,
     *     pagina_actual: int,
     *     ultima_pagina: int,
     *     por_pagina: int
     * }
     * @param array{
     *     busqueda?: string,
     *     estado_id?: int|null,
     *     prioridad_id?: int|null,
     *     tecnico_id?: int|null
     * } $filters
     */
    public function paginateVisibleTo(
        int $userId,
        string $roleName,
        int $requestedPage,
        int $ticketsPerPage = 10,
        array $filters = []
    ): array {
        if ($userId <= 0 || $requestedPage <= 0 || $ticketsPerPage <= 0) {
            throw new InvalidArgumentException('Los parámetros de paginación no son válidos.');
        }

        [$visibilityCondition, $visibilityParameters] = $this->visibilityFor(
            $userId,
            $roleName
        );
        [$filterConditions, $filterParameters] = $this->filtersFor($filters);
        $whereCondition = implode(' AND ', [
            $visibilityCondition,
            ...$filterConditions,
        ]);
        $queryParameters = [
            ...$visibilityParameters,
            ...$filterParameters,
        ];
        $countTicketsStatement = $this->databaseConnection->prepare(
            'SELECT COUNT(*)
             FROM tickets AS t
             WHERE ' . $whereCondition
        );
        $countTicketsStatement->execute($queryParameters);
        $totalTickets = (int) $countTicketsStatement->fetchColumn();
        $lastPage = max(1, (int) ceil($totalTickets / $ticketsPerPage));
        $currentPage = min($requestedPage, $lastPage);
        $offset = ($currentPage - 1) * $ticketsPerPage;

        $listTicketsStatement = $this->databaseConnection->prepare(
            $this->ticketSelect()
            . ' WHERE ' . $whereCondition
            . ' ORDER BY t.created_at DESC, t.id DESC
                LIMIT :limite OFFSET :desplazamiento'
        );

        foreach ($queryParameters as $parameterName => $parameterValue) {
            $listTicketsStatement->bindValue(
                ':' . $parameterName,
                $parameterValue,
                is_int($parameterValue) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $listTicketsStatement->bindValue(':limite', $ticketsPerPage, PDO::PARAM_INT);
        $listTicketsStatement->bindValue(':desplazamiento', $offset, PDO::PARAM_INT);
        $listTicketsStatement->execute();
        $ticketRows = $listTicketsStatement->fetchAll();

        return [
            'tickets' => array_map(
                fn (array $ticketRow): array => $this->mapTicket($ticketRow),
                $ticketRows
            ),
            'total' => $totalTickets,
            'pagina_actual' => $currentPage,
            'ultima_pagina' => $lastPage,
            'por_pagina' => $ticketsPerPage,
        ];
    }

    /**
     * Busca un ticket sólo cuando pertenece al ámbito visible de la identidad.
     *
     * Aplicar la propiedad o asignación en SQL evita recuperar primero un recurso
     * ajeno y mantiene indistinguibles los casos inexistente y no autorizado.
     *
     * @return array<string, mixed>|null
     */
    public function findVisibleByCode(
        string $ticketCode,
        int $userId,
        string $roleName
    ): ?array
    {
        if ($ticketCode === '' || $userId <= 0) {
            throw new InvalidArgumentException('Los datos del ticket no son válidos.');
        }

        [$visibilityCondition, $visibilityParameters] = $this->visibilityFor(
            $userId,
            $roleName
        );
        $findTicketStatement = $this->databaseConnection->prepare(
            $this->ticketSelect()
            . ' WHERE t.codigo = :ticket_codigo
                AND ' . $visibilityCondition . '
                LIMIT 1'
        );
        $findTicketStatement->execute([
            'ticket_codigo' => $ticketCode,
            ...$visibilityParameters,
        ]);
        $ticketRow = $findTicketStatement->fetch();

        return is_array($ticketRow) ? $this->mapTicket($ticketRow) : null;
    }

    /**
     * Busca y bloquea un ticket visible para validar una mutación sin carreras.
     *
     * @return array<string, mixed>|null
     */
    public function findVisibleByCodeForUpdate(
        string $ticketCode,
        int $userId,
        string $roleName
    ): ?array {
        [$visibilityCondition, $visibilityParameters] = $this->visibilityFor(
            $userId,
            $roleName
        );
        $findTicketStatement = $this->databaseConnection->prepare(
            $this->ticketSelect()
            . ' WHERE t.codigo = :ticket_codigo
                AND ' . $visibilityCondition . '
                LIMIT 1
                FOR UPDATE'
        );
        $findTicketStatement->execute([
            'ticket_codigo' => $ticketCode,
            ...$visibilityParameters,
        ]);
        $ticketRow = $findTicketStatement->fetch();

        return is_array($ticketRow) ? $this->mapTicket($ticketRow) : null;
    }

    /**
     * Inserta un ticket con datos ya validados y devuelve su identificador interno.
     */
    public function create(
        string $ticketCode,
        int $clientId,
        int $categoryId,
        int $priorityId,
        int $ticketStatusId,
        string $subject,
        string $description
    ): int {
        $createTicketStatement = $this->databaseConnection->prepare(
            'INSERT INTO tickets (
                codigo,
                cliente_id,
                categoria_id,
                prioridad_id,
                estado_id,
                asunto,
                descripcion
             ) VALUES (
                :codigo,
                :cliente_id,
                :categoria_id,
                :prioridad_id,
                :estado_id,
                :asunto,
                :descripcion
             )'
        );
        $createTicketStatement->execute([
            'codigo' => $ticketCode,
            'cliente_id' => $clientId,
            'categoria_id' => $categoryId,
            'prioridad_id' => $priorityId,
            'estado_id' => $ticketStatusId,
            'asunto' => $subject,
            'descripcion' => $description,
        ]);

        return (int) $this->databaseConnection->lastInsertId();
    }

    /**
     * Actualiza los campos que conforman el contenido original del ticket.
     */
    public function updateOriginal(
        int $ticketId,
        int $categoryId,
        string $subject,
        string $description
    ): void {
        $updateTicketStatement = $this->databaseConnection->prepare(
            'UPDATE tickets
             SET categoria_id = :categoria_id,
                 asunto = :asunto,
                 descripcion = :descripcion
             WHERE id = :ticket_id'
        );
        $updateTicketStatement->execute([
            'ticket_id' => $ticketId,
            'categoria_id' => $categoryId,
            'asunto' => $subject,
            'descripcion' => $description,
        ]);
    }

    /**
     * Asigna o reemplaza al técnico y registra el momento de la asignación actual.
     */
    public function updateTechnician(int $ticketId, int $technicianId): void
    {
        $updateTechnicianStatement = $this->databaseConnection->prepare(
            'UPDATE tickets
             SET tecnico_id = :tecnico_id,
                 fecha_asignacion_at = NOW()
             WHERE id = :ticket_id'
        );
        $updateTechnicianStatement->execute([
            'ticket_id' => $ticketId,
            'tecnico_id' => $technicianId,
        ]);
    }

    /**
     * Cambia el estado y aplica sus efectos sobre resolución y cierre.
     */
    public function updateStatus(
        int $ticketId,
        int $ticketStatusId,
        string $ticketStatusCode
    ): void {
        $statement = $this->databaseConnection->prepare(
            'UPDATE tickets
             SET estado_id = :estado_id,
                 fecha_resolucion_at = CASE
                    WHEN :codigo_resolucion = :codigo_objetivo_resolucion THEN NOW()
                    WHEN :codigo_cierre = :codigo_objetivo_preservacion THEN fecha_resolucion_at
                    ELSE NULL
                 END,
                 fecha_cierre_at = CASE
                    WHEN :codigo_cierre_comparacion = :codigo_objetivo_cierre THEN NOW()
                    ELSE NULL
                 END
             WHERE id = :ticket_id'
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'estado_id' => $ticketStatusId,
            'codigo_resolucion' => EstadoTicket::CODIGO_RESUELTO,
            'codigo_cierre' => EstadoTicket::CODIGO_CERRADO,
            'codigo_objetivo_resolucion' => $ticketStatusCode,
            'codigo_objetivo_preservacion' => $ticketStatusCode,
            'codigo_cierre_comparacion' => EstadoTicket::CODIGO_CERRADO,
            'codigo_objetivo_cierre' => $ticketStatusCode,
        ]);
    }

    /**
     * Cambia la prioridad sin alterar otros datos del flujo.
     */
    public function updatePriority(int $ticketId, int $priorityId): void
    {
        $statement = $this->databaseConnection->prepare(
            'UPDATE tickets SET prioridad_id = :prioridad_id WHERE id = :ticket_id'
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'prioridad_id' => $priorityId,
        ]);
    }

    /**
     * Construye el SELECT común con las relaciones necesarias para listado y detalle.
     */
    private function ticketSelect(): string
    {
        return 'SELECT
                    t.id,
                    t.codigo,
                    t.asunto,
                    t.descripcion,
                    t.created_at,
                    t.updated_at,
                    t.fecha_asignacion_at,
                    t.fecha_resolucion_at,
                    t.fecha_cierre_at,
                    cliente.id AS cliente_id,
                    cliente.nombre AS cliente_nombre,
                    cliente.apellido AS cliente_apellido,
                    cliente.email AS cliente_email,
                    tecnico.id AS tecnico_id,
                    tecnico.nombre AS tecnico_nombre,
                    tecnico.apellido AS tecnico_apellido,
                    categoria.id AS categoria_id,
                    categoria.nombre AS categoria_nombre,
                    categoria.activo AS categoria_activa,
                    prioridad.id AS prioridad_id,
                    prioridad.nombre AS prioridad_nombre,
                    prioridad.color AS prioridad_color,
                    prioridad.activo AS prioridad_activa,
                    estado.id AS estado_id,
                    estado.codigo AS estado_codigo,
                    estado.nombre AS estado_nombre,
                    estado.es_final AS estado_es_final,
                    estado.activo AS estado_activo
                FROM tickets AS t
                INNER JOIN usuarios AS cliente ON cliente.id = t.cliente_id
                LEFT JOIN usuarios AS tecnico ON tecnico.id = t.tecnico_id
                INNER JOIN categorias AS categoria ON categoria.id = t.categoria_id
                INNER JOIN prioridades AS prioridad ON prioridad.id = t.prioridad_id
                INNER JOIN estados_ticket AS estado ON estado.id = t.estado_id';
    }

    /**
     * Traduce el rol autenticado a una condición SQL cerrada y parametrizada.
     *
     * @return array{0: string, 1: array<string, int>}
     */
    private function visibilityFor(int $userId, string $roleName): array
    {
        return match ($roleName) {
            'Administrador' => ['1 = 1', []],
            'Cliente' => ['t.cliente_id = :usuario_id', ['usuario_id' => $userId]],
            'Técnico' => ['t.tecnico_id = :usuario_id', ['usuario_id' => $userId]],
            default => throw new InvalidArgumentException('El rol no puede consultar tickets.'),
        };
    }

    /**
     * Traduce filtros opcionales a condiciones y parámetros seguros de consulta.
     *
     * La búsqueda utiliza `LOCATE` para interpretar `%` y `_` como texto literal
     * y no como comodines aportados por el usuario.
     *
     * @param array{
     *     busqueda?: string,
     *     estado_id?: int|null,
     *     prioridad_id?: int|null,
     *     tecnico_id?: int|null
     * } $filters
     *
     * @return array{0: list<string>, 1: array<string, int|string>}
     */
    private function filtersFor(array $filters): array
    {
        $filterConditions = [];
        $filterParameters = [];
        $searchTerm = trim((string) ($filters['busqueda'] ?? ''));

        if ($searchTerm !== '') {
            $filterConditions[] = '(
                LOCATE(:busqueda_codigo, t.codigo) > 0
                OR LOCATE(:busqueda_asunto, t.asunto) > 0
            )';
            $filterParameters['busqueda_codigo'] = $searchTerm;
            $filterParameters['busqueda_asunto'] = $searchTerm;
        }

        $identifierFilters = [
            'estado_id' => 't.estado_id',
            'prioridad_id' => 't.prioridad_id',
            'tecnico_id' => 't.tecnico_id',
        ];

        foreach ($identifierFilters as $filterName => $columnName) {
            $filterIdentifier = $filters[$filterName] ?? null;

            if (is_int($filterIdentifier) && $filterIdentifier > 0) {
                $filterConditions[] = $columnName . ' = :' . $filterName;
                $filterParameters[$filterName] = $filterIdentifier;
            }
        }

        return [$filterConditions, $filterParameters];
    }

    /**
     * Convierte una fila relacional a los tipos utilizados por las vistas.
     *
     * @param array<string, mixed> $ticketRow
     *
     * @return array<string, mixed>
     */
    private function mapTicket(array $ticketRow): array
    {
        return [
            'id' => (int) $ticketRow['id'],
            'codigo' => (string) $ticketRow['codigo'],
            'asunto' => (string) $ticketRow['asunto'],
            'descripcion' => (string) $ticketRow['descripcion'],
            'created_at' => (string) $ticketRow['created_at'],
            'updated_at' => (string) $ticketRow['updated_at'],
            'fecha_asignacion_at' => $this->nullableString($ticketRow['fecha_asignacion_at']),
            'fecha_resolucion_at' => $this->nullableString($ticketRow['fecha_resolucion_at']),
            'fecha_cierre_at' => $this->nullableString($ticketRow['fecha_cierre_at']),
            'cliente' => [
                'id' => (int) $ticketRow['cliente_id'],
                'nombre' => (string) $ticketRow['cliente_nombre'],
                'apellido' => (string) $ticketRow['cliente_apellido'],
                'email' => (string) $ticketRow['cliente_email'],
            ],
            'tecnico' => $ticketRow['tecnico_id'] === null ? null : [
                'id' => (int) $ticketRow['tecnico_id'],
                'nombre' => (string) $ticketRow['tecnico_nombre'],
                'apellido' => (string) $ticketRow['tecnico_apellido'],
            ],
            'categoria' => [
                'id' => (int) $ticketRow['categoria_id'],
                'nombre' => (string) $ticketRow['categoria_nombre'],
                'activa' => (bool) $ticketRow['categoria_activa'],
            ],
            'prioridad' => [
                'id' => (int) $ticketRow['prioridad_id'],
                'nombre' => (string) $ticketRow['prioridad_nombre'],
                'color' => $this->nullableString($ticketRow['prioridad_color']),
                'activa' => (bool) $ticketRow['prioridad_activa'],
            ],
            'estado' => [
                'id' => (int) $ticketRow['estado_id'],
                'codigo' => (string) $ticketRow['estado_codigo'],
                'nombre' => (string) $ticketRow['estado_nombre'],
                'es_final' => (bool) $ticketRow['estado_es_final'],
                'activo' => (bool) $ticketRow['estado_activo'],
            ],
        ];
    }

    /**
     * Conserva valores SQL nulos y normaliza el resto como texto.
     */
    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
