<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Categoria;
use App\Models\EstadoTicket;
use App\Models\Prioridad;
use App\Models\Ticket;
use App\Models\Usuario;
use App\Services\TicketCreationException;
use App\Services\TicketService;
use App\Services\TicketUpdateException;

final class TicketController extends Controller
{
    private const TICKETS_PER_PAGE = 10;

    private const MAX_SEARCH_LENGTH = 100;

    private ?Ticket $ticketModel;

    private ?Categoria $categoryModel;

    private ?Prioridad $priorityModel;

    private ?TicketService $ticketService;

    private ?Usuario $userModel;

    private ?EstadoTicket $ticketStatusModel;

    /**
     * Permite proporcionar dependencias controladas para pruebas aisladas.
     */
    public function __construct(
        ?Ticket $ticketModel = null,
        ?Categoria $categoryModel = null,
        ?Prioridad $priorityModel = null,
        ?TicketService $ticketService = null,
        ?Usuario $userModel = null,
        ?EstadoTicket $ticketStatusModel = null
    ) {
        $this->ticketModel = $ticketModel;
        $this->categoryModel = $categoryModel;
        $this->priorityModel = $priorityModel;
        $this->ticketService = $ticketService;
        $this->userModel = $userModel;
        $this->ticketStatusModel = $ticketStatusModel;
    }

    /**
     * Muestra la página solicitada dentro del ámbito visible del usuario.
     */
    public function index(Request $request): Response
    {
        $authenticatedUser = Session::user();

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        $filterValues = $this->ticketFilterValues($request);
        $pagination = $this->ticketModel()->paginateVisibleTo(
            $authenticatedUser['id'],
            $authenticatedUser['rol'],
            $this->validPage($request->query('pagina')),
            self::TICKETS_PER_PAGE,
            [
                'busqueda' => $filterValues['busqueda'],
                'estado_id' => $filterValues['estado_id'] === ''
                    ? null
                    : (int) $filterValues['estado_id'],
                'prioridad_id' => $filterValues['prioridad_id'] === ''
                    ? null
                    : (int) $filterValues['prioridad_id'],
                'tecnico_id' => $filterValues['tecnico_id'] === ''
                    ? null
                    : (int) $filterValues['tecnico_id'],
            ]
        );
        $previousPageUrl = $pagination['pagina_actual'] > 1
            ? $this->ticketListPageUrl(
                $pagination['pagina_actual'] - 1,
                $filterValues
            )
            : null;
        $nextPageUrl = $pagination['pagina_actual'] < $pagination['ultima_pagina']
            ? $this->ticketListPageUrl(
                $pagination['pagina_actual'] + 1,
                $filterValues
            )
            : null;
        $ticketsWithActions = array_map(
            fn (array $ticket): array => [
                ...$ticket,
                'acciones' => $this->ticketActions($ticket, $authenticatedUser),
            ],
            $pagination['tickets']
        );

        return $this->render('tickets/index', [
            'title' => 'Tickets | HelpDesk Pro',
            'tickets' => $ticketsWithActions,
            'pagination' => $pagination,
            'usuario' => $authenticatedUser,
            'filterValues' => $filterValues,
            'hasActiveFilters' => array_filter(
                $filterValues,
                static fn (string $filterValue): bool => $filterValue !== ''
            ) !== [],
            'statuses' => $this->ticketStatusModel()->all(),
            'priorities' => $this->priorityModel()->all(),
            'technicians' => $this->userModel()->activeTechnicians(),
            'previousPageUrl' => $previousPageUrl,
            'nextPageUrl' => $nextPageUrl,
        ]);
    }

    /**
     * Presenta un ticket sólo si pertenece o está asignado al usuario, según su rol.
     */
    public function show(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        $ticket = $this->ticketModel()->findVisibleByCode(
            $normalizedTicketCode,
            $authenticatedUser['id'],
            $authenticatedUser['rol']
        );

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        return $this->render('tickets/show', [
            'title' => $ticket['codigo'] . ' | HelpDesk Pro',
            'ticket' => $ticket,
            'usuario' => $authenticatedUser,
            'successMessage' => Session::pullFlash('success'),
            'canEditOriginal' => $this->canShowEditLink($ticket, $authenticatedUser),
        ]);
    }

    /**
     * Muestra al cliente el formulario con los catálogos actualmente activos.
     */
    public function create(Request $request): Response
    {
        $authenticatedUser = Session::user();

        if ($authenticatedUser === null || $authenticatedUser['rol'] !== 'Cliente') {
            return $this->forbiddenResponse();
        }

        return $this->renderCreateForm([
            'categoria_id' => '',
            'prioridad_id' => '',
            'asunto' => '',
            'descripcion' => '',
        ]);
    }

    /**
     * Valida la entrada y delega el alta transaccional del ticket propio.
     */
    public function store(Request $request): Response
    {
        $authenticatedUser = Session::user();

        if ($authenticatedUser === null || $authenticatedUser['rol'] !== 'Cliente') {
            return $this->forbiddenResponse();
        }

        $formValues = $this->ticketFormValues($request);
        $validationErrors = $this->validateTicket($formValues);

        if ($validationErrors !== []) {
            return $this->renderCreateForm($formValues, $validationErrors, 422);
        }

        try {
            $ticketCode = $this->ticketService()->createForClient(
                $authenticatedUser['id'],
                $authenticatedUser['rol'],
                (int) $formValues['categoria_id'],
                (int) $formValues['prioridad_id'],
                $formValues['asunto'],
                $formValues['descripcion']
            );
        } catch (TicketCreationException $exception) {
            if ($exception->field() === 'autorizacion') {
                return $this->forbiddenResponse();
            }

            return $this->renderCreateForm(
                $formValues,
                [$exception->field() => $exception->getMessage()],
                422
            );
        }

        Session::flash('success', 'Ticket creado correctamente.');

        return $this->redirect('/tickets/' . rawurlencode($ticketCode), 303);
    }

    /**
     * Muestra los campos originales cuando el rol y estado permiten editarlos.
     */
    public function edit(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findEditableOriginal(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        return $this->renderEditForm($ticket, [
            'categoria_id' => (string) $ticket['categoria']['id'],
            'asunto' => $ticket['asunto'],
            'descripcion' => $ticket['descripcion'],
        ]);
    }

    /**
     * Revalida permisos y actualiza el contenido original de forma transaccional.
     */
    public function update(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findEditableOriginal(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        $formValues = $this->ticketEditFormValues($request);
        $validationErrors = $this->validateTicketEdit($formValues);

        if ($validationErrors !== []) {
            return $this->renderEditForm($ticket, $formValues, $validationErrors, 422);
        }

        try {
            $ticketUpdated = $this->ticketService()->updateOriginal(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol'],
                (int) $formValues['categoria_id'],
                $formValues['asunto'],
                $formValues['descripcion']
            );
        } catch (TicketUpdateException $exception) {
            if ($exception->statusCode() !== 422) {
                return $this->ticketUpdateErrorResponse($exception);
            }

            return $this->renderEditForm(
                $ticket,
                $formValues,
                [$exception->field() => $exception->getMessage()],
                422
            );
        }

        Session::flash(
            'success',
            $ticketUpdated
                ? 'Ticket actualizado correctamente.'
                : 'No se detectaron cambios en el ticket.'
        );

        return $this->redirect('/tickets/' . rawurlencode($normalizedTicketCode), 303);
    }

    /**
     * Muestra al administrador el técnico actual y los candidatos activos.
     */
    public function assignment(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findAssignable(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        return $this->renderAssignmentForm($ticket);
    }

    /**
     * Valida y aplica una asignación o reasignación técnica administrativa.
     */
    public function updateAssignment(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findAssignable(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        $submittedTechnicianId = $request->input('tecnico_id');
        $technicianId = is_string($submittedTechnicianId)
            ? trim($submittedTechnicianId)
            : '';

        if (!$this->isPositiveIdentifier($technicianId)) {
            return $this->renderAssignmentForm(
                $ticket,
                $technicianId,
                'Debe seleccionar un técnico válido.',
                422
            );
        }

        try {
            $ticketAssigned = $this->ticketService()->assignTechnician(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol'],
                (int) $technicianId
            );
        } catch (TicketUpdateException $exception) {
            if ($exception->statusCode() !== 422) {
                return $this->ticketUpdateErrorResponse($exception);
            }

            return $this->renderAssignmentForm(
                $ticket,
                $technicianId,
                $exception->getMessage(),
                422
            );
        }

        Session::flash(
            'success',
            $ticketAssigned
                ? 'Técnico asignado correctamente.'
                : 'El ticket ya estaba asignado al técnico seleccionado.'
        );

        return $this->redirect('/tickets/' . rawurlencode($normalizedTicketCode), 303);
    }

    /**
     * Muestra al administrador o técnico asignado el formulario de flujo.
     */
    public function workflow(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findManageable(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        return $this->renderWorkflowForm($ticket);
    }

    /**
     * Valida y aplica cambios de estado y prioridad autorizados.
     */
    public function updateWorkflow(Request $request, string $ticketCode): Response
    {
        $authenticatedUser = Session::user();
        $normalizedTicketCode = $this->validTicketCode($ticketCode);

        if ($authenticatedUser === null) {
            return $this->forbiddenResponse();
        }

        if ($normalizedTicketCode === null) {
            return $this->notFoundResponse();
        }

        try {
            $ticket = $this->ticketService()->findManageable(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol']
            );
        } catch (TicketUpdateException $exception) {
            return $this->ticketUpdateErrorResponse($exception);
        }

        if ($ticket === null) {
            return $this->notFoundResponse();
        }

        $submittedStatusId = $request->input('estado_id');
        $submittedPriorityId = $request->input('prioridad_id');
        $statusId = is_string($submittedStatusId)
            ? trim($submittedStatusId)
            : '';
        $priorityId = is_string($submittedPriorityId)
            ? trim($submittedPriorityId)
            : '';
        $validationErrors = [];

        if (!$this->isPositiveIdentifier($statusId)) {
            $validationErrors['estado_id'] = 'Debe seleccionar un estado válido.';
        }

        if (!$this->isPositiveIdentifier($priorityId)) {
            $validationErrors['prioridad_id'] = 'Debe seleccionar una prioridad válida.';
        }

        if ($validationErrors !== []) {
            return $this->renderWorkflowForm(
                $ticket,
                $statusId,
                $priorityId,
                $validationErrors,
                422
            );
        }

        try {
            $updated = $this->ticketService()->updateWorkflow(
                $normalizedTicketCode,
                $authenticatedUser['id'],
                $authenticatedUser['rol'],
                (int) $statusId,
                (int) $priorityId
            );
        } catch (TicketUpdateException $exception) {
            if ($exception->statusCode() !== 422) {
                return $this->ticketUpdateErrorResponse($exception);
            }

            return $this->renderWorkflowForm(
                $ticket,
                $statusId,
                $priorityId,
                [$exception->field() => $exception->getMessage()],
                422
            );
        }

        Session::flash(
            'success',
            $updated
                ? 'Flujo del ticket actualizado correctamente.'
                : 'No se detectaron cambios de estado o prioridad.'
        );

        return $this->redirect('/tickets/' . rawurlencode($normalizedTicketCode), 303);
    }

    /**
     * Renderiza estados y prioridades activos conservando valores históricos.
     *
     * @param array<string, mixed> $ticket
     * @param array<string, string> $validationErrors
     */
    private function renderWorkflowForm(
        array $ticket,
        string $selectedStatusId = '',
        string $selectedPriorityId = '',
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        $statuses = $this->ticketStatusModel()->active();
        $priorities = $this->priorityModel()->active();
        $selectedStatusId = $selectedStatusId !== ''
            ? $selectedStatusId
            : (string) $ticket['estado']['id'];
        $selectedPriorityId = $selectedPriorityId !== ''
            ? $selectedPriorityId
            : (string) $ticket['prioridad']['id'];

        if (!$ticket['estado']['activo']) {
            $statuses[] = [
                ...$ticket['estado'],
                'nombre' => $ticket['estado']['nombre'] . ' (inactivo)',
            ];
        }

        if (!$ticket['prioridad']['activa']) {
            $priorities[] = [
                ...$ticket['prioridad'],
                'nombre' => $ticket['prioridad']['nombre'] . ' (inactiva)',
            ];
        }

        return $this->render('tickets/workflow', [
            'title' => 'Gestionar ' . $ticket['codigo'] . ' | HelpDesk Pro',
            'ticket' => $ticket,
            'statuses' => $statuses,
            'priorities' => $priorities,
            'selectedStatusId' => $selectedStatusId,
            'selectedPriorityId' => $selectedPriorityId,
            'validationErrors' => $validationErrors,
            'csrfToken' => Session::csrfToken(),
        ], $statusCode);
    }

    /**
     * Renderiza el formulario administrativo con técnicos activos.
     *
     * @param array<string, mixed> $ticket
     */
    private function renderAssignmentForm(
        array $ticket,
        string $selectedTechnicianId = '',
        ?string $validationError = null,
        int $statusCode = 200
    ): Response {
        if ($selectedTechnicianId === '' && $ticket['tecnico'] !== null) {
            $selectedTechnicianId = (string) $ticket['tecnico']['id'];
        }

        return $this->render('tickets/assignment', [
            'title' => 'Asignar ' . $ticket['codigo'] . ' | HelpDesk Pro',
            'ticket' => $ticket,
            'technicians' => $this->userModel()->activeTechnicians(),
            'selectedTechnicianId' => $selectedTechnicianId,
            'validationError' => $validationError,
            'csrfToken' => Session::csrfToken(),
        ], $statusCode);
    }

    /**
     * Normaliza los campos admitidos durante la edición del contenido original.
     *
     * @return array{categoria_id: string, asunto: string, descripcion: string}
     */
    private function ticketEditFormValues(Request $request): array
    {
        $submittedCategoryId = $request->input('categoria_id');
        $submittedSubject = $request->input('asunto');
        $submittedDescription = $request->input('descripcion');

        return [
            'categoria_id' => is_string($submittedCategoryId)
                ? trim($submittedCategoryId)
                : '',
            'asunto' => is_string($submittedSubject) ? trim($submittedSubject) : '',
            'descripcion' => is_string($submittedDescription)
                ? trim($submittedDescription)
                : '',
        ];
    }

    /**
     * Valida los campos editables sin incluir prioridad, estado ni asignación.
     *
     * @param array{categoria_id: string, asunto: string, descripcion: string} $formValues
     *
     * @return array<string, string>
     */
    private function validateTicketEdit(array $formValues): array
    {
        $validationErrors = [];

        if (!$this->isPositiveIdentifier($formValues['categoria_id'])) {
            $validationErrors['categoria_id'] = 'Debe seleccionar una categoría válida.';
        }

        if ($formValues['asunto'] === '') {
            $validationErrors['asunto'] = 'El asunto es obligatorio.';
        } elseif (mb_strlen($formValues['asunto']) > 180) {
            $validationErrors['asunto'] = 'El asunto no puede superar los 180 caracteres.';
        }

        if ($formValues['descripcion'] === '') {
            $validationErrors['descripcion'] = 'La descripción es obligatoria.';
        } elseif (mb_strlen($formValues['descripcion']) > 5000) {
            $validationErrors['descripcion'] = 'La descripción no puede superar los 5000 caracteres.';
        }

        return $validationErrors;
    }

    /**
     * Renderiza el formulario de edición incluyendo la categoría histórica actual.
     *
     * @param array<string, mixed> $ticket
     * @param array{categoria_id: string, asunto: string, descripcion: string} $formValues
     * @param array<string, string> $validationErrors
     */
    private function renderEditForm(
        array $ticket,
        array $formValues,
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        $categories = $this->categoryModel()->active();

        if (!$ticket['categoria']['activa']) {
            $categories[] = [
                'id' => $ticket['categoria']['id'],
                'nombre' => $ticket['categoria']['nombre'] . ' (inactiva)',
                'descripcion' => null,
                'activo' => false,
            ];
        }

        return $this->render('tickets/edit', [
            'title' => 'Editar ' . $ticket['codigo'] . ' | HelpDesk Pro',
            'ticket' => $ticket,
            'categories' => $categories,
            'formValues' => $formValues,
            'validationErrors' => $validationErrors,
            'csrfToken' => Session::csrfToken(),
        ], $statusCode);
    }

    /**
     * Convierte un rechazo de negocio en la respuesta HTTP correspondiente.
     */
    private function ticketUpdateErrorResponse(TicketUpdateException $exception): Response
    {
        return $exception->statusCode() === 404
            ? $this->notFoundResponse()
            : $this->forbiddenResponse();
    }

    /**
     * Decide únicamente la visibilidad del enlace; el servicio reautoriza la acción.
     *
     * @param array<string, mixed> $ticket
     * @param array{id: int, nombre: string, apellido: string, rol_id: int, rol: string} $user
     */
    private function canShowEditLink(array $ticket, array $user): bool
    {
        return $user['rol'] === 'Administrador'
            || (
                $user['rol'] === 'Cliente'
                && $ticket['cliente']['id'] === $user['id']
                && $ticket['estado']['codigo'] === EstadoTicket::CODIGO_ABIERTO
                && $ticket['tecnico'] === null
            );
    }

    /**
     * Expone acciones contextuales del listado sin reemplazar su autorización backend.
     *
     * @param array<string, mixed> $ticket
     * @param array{id: int, nombre: string, apellido: string, rol_id: int, rol: string} $user
     *
     * @return array{editar: bool, asignar: bool, gestionar: bool}
     */
    private function ticketActions(array $ticket, array $user): array
    {
        $assignedTechnicianId = $ticket['tecnico']['id'] ?? null;

        return [
            'editar' => $this->canShowEditLink($ticket, $user),
            'asignar' => $user['rol'] === 'Administrador',
            'gestionar' => $user['rol'] === 'Administrador'
                || (
                    $user['rol'] === 'Técnico'
                    && $assignedTechnicianId === $user['id']
                ),
        ];
    }

    /**
     * Obtiene el modelo de usuarios que provee técnicos activos.
     */
    private function userModel(): Usuario
    {
        if ($this->userModel === null) {
            $this->userModel = new Usuario();
        }

        return $this->userModel;
    }

    /**
     * Obtiene los estados activos disponibles para el formulario de flujo.
     */
    private function ticketStatusModel(): EstadoTicket
    {
        if ($this->ticketStatusModel === null) {
            $this->ticketStatusModel = new EstadoTicket();
        }

        return $this->ticketStatusModel;
    }

    /**
     * Normaliza los valores escalares recibidos desde el formulario de alta.
     *
     * @return array{
     *     categoria_id: string,
     *     prioridad_id: string,
     *     asunto: string,
     *     descripcion: string
     * }
     */
    private function ticketFormValues(Request $request): array
    {
        $submittedCategoryId = $request->input('categoria_id');
        $submittedPriorityId = $request->input('prioridad_id');
        $submittedSubject = $request->input('asunto');
        $submittedDescription = $request->input('descripcion');

        return [
            'categoria_id' => is_string($submittedCategoryId)
                ? trim($submittedCategoryId)
                : '',
            'prioridad_id' => is_string($submittedPriorityId)
                ? trim($submittedPriorityId)
                : '',
            'asunto' => is_string($submittedSubject) ? trim($submittedSubject) : '',
            'descripcion' => is_string($submittedDescription)
                ? trim($submittedDescription)
                : '',
        ];
    }

    /**
     * Comprueba identificadores y límites antes de iniciar consultas o transacciones.
     *
     * @param array{
     *     categoria_id: string,
     *     prioridad_id: string,
     *     asunto: string,
     *     descripcion: string
     * } $formValues
     *
     * @return array<string, string>
     */
    private function validateTicket(array $formValues): array
    {
        $validationErrors = [];

        if (!$this->isPositiveIdentifier($formValues['categoria_id'])) {
            $validationErrors['categoria_id'] = 'Debe seleccionar una categoría válida.';
        }

        if (!$this->isPositiveIdentifier($formValues['prioridad_id'])) {
            $validationErrors['prioridad_id'] = 'Debe seleccionar una prioridad válida.';
        }

        if ($formValues['asunto'] === '') {
            $validationErrors['asunto'] = 'El asunto es obligatorio.';
        } elseif (mb_strlen($formValues['asunto']) > 180) {
            $validationErrors['asunto'] = 'El asunto no puede superar los 180 caracteres.';
        }

        if ($formValues['descripcion'] === '') {
            $validationErrors['descripcion'] = 'La descripción es obligatoria.';
        } elseif (mb_strlen($formValues['descripcion']) > 5000) {
            $validationErrors['descripcion'] = 'La descripción no puede superar los 5000 caracteres.';
        }

        return $validationErrors;
    }

    /**
     * Indica si el valor representa un identificador entero positivo.
     */
    private function isPositiveIdentifier(string $submittedIdentifier): bool
    {
        return ctype_digit($submittedIdentifier) && (int) $submittedIdentifier > 0;
    }

    /**
     * Normaliza los filtros GET sin aceptar estructuras ni identificadores inválidos.
     *
     * @return array{
     *     busqueda: string,
     *     estado_id: string,
     *     prioridad_id: string,
     *     tecnico_id: string
     * }
     */
    private function ticketFilterValues(Request $request): array
    {
        $submittedSearch = $request->query('busqueda');
        $searchTerm = is_string($submittedSearch) ? trim($submittedSearch) : '';

        if (mb_strlen($searchTerm) > self::MAX_SEARCH_LENGTH) {
            $searchTerm = mb_substr($searchTerm, 0, self::MAX_SEARCH_LENGTH);
        }

        return [
            'busqueda' => $searchTerm,
            'estado_id' => $this->normalizedFilterIdentifier(
                $request->query('estado_id')
            ),
            'prioridad_id' => $this->normalizedFilterIdentifier(
                $request->query('prioridad_id')
            ),
            'tecnico_id' => $this->normalizedFilterIdentifier(
                $request->query('tecnico_id')
            ),
        ];
    }

    /**
     * Conserva un identificador positivo como texto para mostrarlo en el formulario.
     */
    private function normalizedFilterIdentifier(mixed $submittedIdentifier): string
    {
        if (!is_string($submittedIdentifier) && !is_int($submittedIdentifier)) {
            return '';
        }

        $normalizedIdentifier = trim((string) $submittedIdentifier);

        return $this->isPositiveIdentifier($normalizedIdentifier)
            ? $normalizedIdentifier
            : '';
    }

    /**
     * Genera una URL de paginación conservando únicamente filtros no vacíos.
     *
     * @param array{
     *     busqueda: string,
     *     estado_id: string,
     *     prioridad_id: string,
     *     tecnico_id: string
     * } $filterValues
     */
    private function ticketListPageUrl(int $page, array $filterValues): string
    {
        $queryParameters = array_filter(
            $filterValues,
            static fn (string $filterValue): bool => $filterValue !== ''
        );
        $queryParameters['pagina'] = $page;

        return '/tickets?' . http_build_query(
            $queryParameters,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    /**
     * Renderiza el formulario de alta con catálogos activos y valores conservados.
     *
     * @param array{
     *     categoria_id: string,
     *     prioridad_id: string,
     *     asunto: string,
     *     descripcion: string
     * } $formValues
     * @param array<string, string> $validationErrors
     */
    private function renderCreateForm(
        array $formValues,
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        return $this->render('tickets/create', [
            'title' => 'Nuevo ticket | HelpDesk Pro',
            'categories' => $this->categoryModel()->active(),
            'priorities' => $this->priorityModel()->active(),
            'formValues' => $formValues,
            'validationErrors' => $validationErrors,
            'csrfToken' => Session::csrfToken(),
        ], $statusCode);
    }

    /**
     * Normaliza una página positiva y utiliza la primera ante valores manipulados.
     */
    private function validPage(mixed $submittedPage): int
    {
        if (!is_string($submittedPage) && !is_int($submittedPage)) {
            return 1;
        }

        $page = (string) $submittedPage;

        if (!ctype_digit($page)) {
            return 1;
        }

        $normalizedPage = (int) $page;

        return $normalizedPage > 0 ? $normalizedPage : 1;
    }

    /**
     * Normaliza y valida el código público recibido en la ruta de detalle.
     */
    private function validTicketCode(string $ticketCode): ?string
    {
        $normalizedTicketCode = strtoupper(trim($ticketCode));

        if (preg_match('/^[A-Z0-9-]{1,30}$/', $normalizedTicketCode) !== 1) {
            return null;
        }

        return $normalizedTicketCode;
    }

    /**
     * Renderiza una respuesta indistinguible para tickets inexistentes o ajenos.
     */
    private function notFoundResponse(): Response
    {
        return $this->render(
            'errors/404',
            ['title' => 'Ticket no encontrado'],
            404
        );
    }

    /**
     * Mantiene el controlador cerrado si se invoca fuera del pipeline autenticado.
     */
    private function forbiddenResponse(): Response
    {
        return $this->render(
            'errors/403',
            ['title' => 'Acceso denegado'],
            403
        );
    }

    /**
     * Obtiene el modelo sin abrir una conexión hasta que una acción consulta datos.
     */
    private function ticketModel(): Ticket
    {
        if ($this->ticketModel === null) {
            $this->ticketModel = new Ticket();
        }

        return $this->ticketModel;
    }

    /**
     * Obtiene el catálogo de categorías utilizado por el formulario.
     */
    private function categoryModel(): Categoria
    {
        if ($this->categoryModel === null) {
            $this->categoryModel = new Categoria();
        }

        return $this->categoryModel;
    }

    /**
     * Obtiene el catálogo de prioridades utilizado por el formulario.
     */
    private function priorityModel(): Prioridad
    {
        if ($this->priorityModel === null) {
            $this->priorityModel = new Prioridad();
        }

        return $this->priorityModel;
    }

    /**
     * Obtiene el servicio que coordina la creación y su auditoría.
     */
    private function ticketService(): TicketService
    {
        if ($this->ticketService === null) {
            $this->ticketService = new TicketService();
        }

        return $this->ticketService;
    }
}
