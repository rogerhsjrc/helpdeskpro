<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\EstadoTicket;
use PDOException;

final class EstadoTicketController extends Controller
{
    private ?EstadoTicket $ticketStatusModel;

    /**
     * Permite proporcionar un modelo controlado para pruebas aisladas.
     */
    public function __construct(?EstadoTicket $ticketStatusModel = null)
    {
        $this->ticketStatusModel = $ticketStatusModel;
    }

    /**
     * Muestra los estados ordenados según su posición configurada.
     */
    public function index(Request $request): Response
    {
        return $this->render('estados-ticket/index', [
            'title' => 'Estados de ticket | HelpDesk Pro',
            'ticketStatuses' => $this->ticketStatusModel()->all(),
            'csrfToken' => Session::csrfToken(),
            'successMessage' => Session::pullFlash('success'),
        ]);
    }

    /**
     * Muestra el formulario vacío para registrar un estado.
     */
    public function create(Request $request): Response
    {
        return $this->renderTicketStatusForm(
            'Nuevo estado de ticket',
            '/admin/estados-ticket',
            'Crear estado',
            [
                'nombre' => '',
                'descripcion' => '',
                'orden' => '',
                'es_final' => '0',
            ]
        );
    }

    /**
     * Valida y registra un nuevo estado de ticket.
     */
    public function store(Request $request): Response
    {
        $formValues = $this->ticketStatusFormValues($request);
        $validationErrors = $this->validateTicketStatus($formValues);
        $validationErrors = $this->addUniquenessErrors(
            $formValues,
            $validationErrors
        );

        if ($validationErrors !== []) {
            return $this->renderTicketStatusForm(
                'Nuevo estado de ticket',
                '/admin/estados-ticket',
                'Crear estado',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->ticketStatusModel()->create(
                $formValues['nombre'],
                $this->nullableDescription($formValues['descripcion']),
                (int) $formValues['orden'],
                $formValues['es_final'] === '1'
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderTicketStatusForm(
                'Nuevo estado de ticket',
                '/admin/estados-ticket',
                'Crear estado',
                $formValues,
                ['general' => 'El nombre o el orden ya están en uso.'],
                422
            );
        }

        Session::flash('success', 'Estado de ticket creado correctamente.');

        return $this->redirect('/admin/estados-ticket', 303);
    }

    /**
     * Muestra un estado existente en el formulario de edición.
     */
    public function edit(Request $request, string $ticketStatusId): Response
    {
        $normalizedTicketStatusId = $this->validTicketStatusId($ticketStatusId);
        $ticketStatus = $normalizedTicketStatusId === null
            ? null
            : $this->ticketStatusModel()->findById($normalizedTicketStatusId);

        if ($ticketStatus === null) {
            return $this->notFoundResponse();
        }

        return $this->renderTicketStatusForm(
            'Editar estado de ticket',
            sprintf(
                '/admin/estados-ticket/%d/actualizar',
                $ticketStatus['id']
            ),
            'Guardar cambios',
            [
                'nombre' => $ticketStatus['nombre'],
                'descripcion' => $ticketStatus['descripcion'] ?? '',
                'orden' => (string) $ticketStatus['orden'],
                'es_final' => $ticketStatus['es_final'] ? '1' : '0',
            ]
        );
    }

    /**
     * Valida y actualiza un estado de ticket existente.
     */
    public function update(Request $request, string $ticketStatusId): Response
    {
        $normalizedTicketStatusId = $this->validTicketStatusId($ticketStatusId);
        $ticketStatus = $normalizedTicketStatusId === null
            ? null
            : $this->ticketStatusModel()->findById($normalizedTicketStatusId);

        if ($ticketStatus === null || $normalizedTicketStatusId === null) {
            return $this->notFoundResponse();
        }

        $formValues = $this->ticketStatusFormValues($request);
        $validationErrors = $this->validateTicketStatus($formValues);
        $validationErrors = $this->addUniquenessErrors(
            $formValues,
            $validationErrors,
            $normalizedTicketStatusId
        );
        $formAction = sprintf(
            '/admin/estados-ticket/%d/actualizar',
            $normalizedTicketStatusId
        );

        if ($validationErrors !== []) {
            return $this->renderTicketStatusForm(
                'Editar estado de ticket',
                $formAction,
                'Guardar cambios',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->ticketStatusModel()->update(
                $normalizedTicketStatusId,
                $formValues['nombre'],
                $this->nullableDescription($formValues['descripcion']),
                (int) $formValues['orden'],
                $formValues['es_final'] === '1'
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderTicketStatusForm(
                'Editar estado de ticket',
                $formAction,
                'Guardar cambios',
                $formValues,
                ['general' => 'El nombre o el orden ya están en uso.'],
                422
            );
        }

        Session::flash('success', 'Estado de ticket actualizado correctamente.');

        return $this->redirect('/admin/estados-ticket', 303);
    }

    /**
     * Activa o desactiva un estado mediante una acción explícita.
     */
    public function updateStatus(Request $request, string $ticketStatusId): Response
    {
        $normalizedTicketStatusId = $this->validTicketStatusId($ticketStatusId);
        $ticketStatus = $normalizedTicketStatusId === null
            ? null
            : $this->ticketStatusModel()->findById($normalizedTicketStatusId);
        $submittedStatus = $request->input('activo');

        if ($ticketStatus === null || $normalizedTicketStatusId === null) {
            return $this->notFoundResponse();
        }

        if (!is_string($submittedStatus) || !in_array($submittedStatus, ['0', '1'], true)) {
            return $this->render(
                'errors/422',
                [
                    'title' => 'Estado no válido',
                    'message' => 'El estado indicado no es válido.',
                    'backUrl' => '/admin/estados-ticket',
                    'backLabel' => 'Volver a estados de ticket',
                ],
                422
            );
        }

        $ticketStatusActive = $submittedStatus === '1';
        $this->ticketStatusModel()->updateActiveStatus(
            $normalizedTicketStatusId,
            $ticketStatusActive
        );
        Session::flash(
            'success',
            $ticketStatusActive
                ? 'Estado de ticket activado correctamente.'
                : 'Estado de ticket desactivado correctamente.'
        );

        return $this->redirect('/admin/estados-ticket', 303);
    }

    /**
     * Obtiene y normaliza los valores editables enviados por el formulario.
     *
     * @return array{
     *     nombre: string,
     *     descripcion: string,
     *     orden: string,
     *     es_final: string
     * }
     */
    private function ticketStatusFormValues(Request $request): array
    {
        $submittedName = $request->input('nombre');
        $submittedDescription = $request->input('descripcion');
        $submittedOrder = $request->input('orden');
        $submittedFinalStatus = $request->input('es_final');

        if ($submittedFinalStatus === null) {
            $normalizedFinalStatus = '0';
        } elseif (is_string($submittedFinalStatus)) {
            $normalizedFinalStatus = $submittedFinalStatus;
        } else {
            $normalizedFinalStatus = 'invalid';
        }

        return [
            'nombre' => is_string($submittedName) ? trim($submittedName) : '',
            'descripcion' => is_string($submittedDescription)
                ? trim($submittedDescription)
                : '',
            'orden' => is_string($submittedOrder) ? trim($submittedOrder) : '',
            'es_final' => $normalizedFinalStatus,
        ];
    }

    /**
     * Valida los campos propios de un estado antes de consultar la base.
     *
     * @param array{
     *     nombre: string,
     *     descripcion: string,
     *     orden: string,
     *     es_final: string
     * } $formValues
     *
     * @return array<string, string>
     */
    private function validateTicketStatus(array $formValues): array
    {
        $validationErrors = [];

        if ($formValues['nombre'] === '') {
            $validationErrors['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($formValues['nombre']) > 60) {
            $validationErrors['nombre'] = 'El nombre no puede superar los 60 caracteres.';
        }

        if (mb_strlen($formValues['descripcion']) > 255) {
            $validationErrors['descripcion'] = 'La descripción no puede superar los 255 caracteres.';
        }

        if ($formValues['orden'] === '') {
            $validationErrors['orden'] = 'El orden es obligatorio.';
        } elseif (
            !ctype_digit($formValues['orden'])
            || (int) $formValues['orden'] < 1
            || (int) $formValues['orden'] > 255
        ) {
            $validationErrors['orden'] = 'El orden debe ser un entero entre 1 y 255.';
        }

        if (!in_array($formValues['es_final'], ['0', '1'], true)) {
            $validationErrors['es_final'] = 'El indicador de estado final no es válido.';
        }

        return $validationErrors;
    }

    /**
     * Agrega conflictos de nombre y orden sólo cuando sus formatos son válidos.
     *
     * @param array{
     *     nombre: string,
     *     descripcion: string,
     *     orden: string,
     *     es_final: string
     * } $formValues
     * @param array<string, string> $validationErrors
     *
     * @return array<string, string>
     */
    private function addUniquenessErrors(
        array $formValues,
        array $validationErrors,
        ?int $excludedTicketStatusId = null
    ): array {
        if (
            !isset($validationErrors['nombre'])
            && $this->ticketStatusModel()->nameExists(
                $formValues['nombre'],
                $excludedTicketStatusId
            )
        ) {
            $validationErrors['nombre'] = 'Ya existe un estado con ese nombre.';
        }

        if (
            !isset($validationErrors['orden'])
            && $this->ticketStatusModel()->orderExists(
                (int) $formValues['orden'],
                $excludedTicketStatusId
            )
        ) {
            $validationErrors['orden'] = 'Ya existe un estado con ese orden.';
        }

        return $validationErrors;
    }

    /**
     * Renderiza el formulario compartido de alta y edición.
     *
     * @param array{
     *     nombre: string,
     *     descripcion: string,
     *     orden: string,
     *     es_final: string
     * } $formValues
     * @param array<string, string> $validationErrors
     */
    private function renderTicketStatusForm(
        string $heading,
        string $formAction,
        string $submitLabel,
        array $formValues,
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        return $this->render('estados-ticket/form', [
            'title' => $heading . ' | HelpDesk Pro',
            'heading' => $heading,
            'formAction' => $formAction,
            'submitLabel' => $submitLabel,
            'formValues' => $formValues,
            'validationErrors' => $validationErrors,
            'csrfToken' => Session::csrfToken(),
        ], $statusCode);
    }

    /**
     * Convierte una descripción vacía al valor nulo utilizado por la base.
     */
    private function nullableDescription(string $ticketStatusDescription): ?string
    {
        return $ticketStatusDescription === '' ? null : $ticketStatusDescription;
    }

    /**
     * Convierte un parámetro positivo a identificador de estado.
     */
    private function validTicketStatusId(string $ticketStatusId): ?int
    {
        if (!ctype_digit($ticketStatusId)) {
            return null;
        }

        $normalizedTicketStatusId = (int) $ticketStatusId;

        return $normalizedTicketStatusId > 0 ? $normalizedTicketStatusId : null;
    }

    /**
     * Identifica conflictos de unicidad conservando otros errores de persistencia.
     */
    private function isUniqueConstraintViolation(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    /**
     * Renderiza la respuesta estándar cuando el estado solicitado no existe.
     */
    private function notFoundResponse(): Response
    {
        return $this->render(
            'errors/404',
            ['title' => 'Estado de ticket no encontrado'],
            404
        );
    }

    /**
     * Obtiene el modelo sin conectar la base en acciones que no consultan datos.
     */
    private function ticketStatusModel(): EstadoTicket
    {
        if ($this->ticketStatusModel === null) {
            $this->ticketStatusModel = new EstadoTicket();
        }

        return $this->ticketStatusModel;
    }
}
