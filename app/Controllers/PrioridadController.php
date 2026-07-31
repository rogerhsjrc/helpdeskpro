<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Prioridad;
use PDOException;

final class PrioridadController extends Controller
{
    private ?Prioridad $priorityModel;

    /**
     * Permite proporcionar un modelo controlado para pruebas aisladas.
     */
    public function __construct(?Prioridad $priorityModel = null)
    {
        $this->priorityModel = $priorityModel;
    }

    /**
     * Muestra las prioridades ordenadas por nivel de impacto.
     */
    public function index(Request $request): Response
    {
        return $this->render('prioridades/index', [
            'title' => 'Prioridades | HelpDesk Pro',
            'priorities' => $this->priorityModel()->all(),
            'csrfToken' => Session::csrfToken(),
            'successMessage' => Session::pullFlash('success'),
        ]);
    }

    /**
     * Muestra el formulario vacío para registrar una prioridad.
     */
    public function create(Request $request): Response
    {
        return $this->renderPriorityForm(
            'Nueva prioridad',
            '/admin/prioridades',
            'Crear prioridad',
            [
                'nombre' => '',
                'nivel' => '',
                'descripcion' => '',
                'color' => '',
            ]
        );
    }

    /**
     * Valida y registra una nueva prioridad.
     */
    public function store(Request $request): Response
    {
        $formValues = $this->priorityFormValues($request);
        $validationErrors = $this->validatePriority($formValues);
        $validationErrors = $this->addUniquenessErrors(
            $formValues,
            $validationErrors
        );

        if ($validationErrors !== []) {
            return $this->renderPriorityForm(
                'Nueva prioridad',
                '/admin/prioridades',
                'Crear prioridad',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->priorityModel()->create(
                $formValues['nombre'],
                (int) $formValues['nivel'],
                $this->nullableValue($formValues['descripcion']),
                $this->nullableValue($formValues['color'])
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderPriorityForm(
                'Nueva prioridad',
                '/admin/prioridades',
                'Crear prioridad',
                $formValues,
                ['general' => 'El nombre o el nivel ya están en uso.'],
                422
            );
        }

        Session::flash('success', 'Prioridad creada correctamente.');

        return $this->redirect('/admin/prioridades', 303);
    }

    /**
     * Muestra una prioridad existente en el formulario de edición.
     */
    public function edit(Request $request, string $priorityId): Response
    {
        $normalizedPriorityId = $this->validPriorityId($priorityId);
        $priority = $normalizedPriorityId === null
            ? null
            : $this->priorityModel()->findById($normalizedPriorityId);

        if ($priority === null) {
            return $this->notFoundResponse();
        }

        return $this->renderPriorityForm(
            'Editar prioridad',
            sprintf('/admin/prioridades/%d/actualizar', $priority['id']),
            'Guardar cambios',
            [
                'nombre' => $priority['nombre'],
                'nivel' => (string) $priority['nivel'],
                'descripcion' => $priority['descripcion'] ?? '',
                'color' => $priority['color'] ?? '',
            ]
        );
    }

    /**
     * Valida y actualiza una prioridad existente.
     */
    public function update(Request $request, string $priorityId): Response
    {
        $normalizedPriorityId = $this->validPriorityId($priorityId);
        $priority = $normalizedPriorityId === null
            ? null
            : $this->priorityModel()->findById($normalizedPriorityId);

        if ($priority === null || $normalizedPriorityId === null) {
            return $this->notFoundResponse();
        }

        $formValues = $this->priorityFormValues($request);
        $validationErrors = $this->validatePriority($formValues);
        $validationErrors = $this->addUniquenessErrors(
            $formValues,
            $validationErrors,
            $normalizedPriorityId
        );
        $formAction = sprintf(
            '/admin/prioridades/%d/actualizar',
            $normalizedPriorityId
        );

        if ($validationErrors !== []) {
            return $this->renderPriorityForm(
                'Editar prioridad',
                $formAction,
                'Guardar cambios',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->priorityModel()->update(
                $normalizedPriorityId,
                $formValues['nombre'],
                (int) $formValues['nivel'],
                $this->nullableValue($formValues['descripcion']),
                $this->nullableValue($formValues['color'])
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderPriorityForm(
                'Editar prioridad',
                $formAction,
                'Guardar cambios',
                $formValues,
                ['general' => 'El nombre o el nivel ya están en uso.'],
                422
            );
        }

        Session::flash('success', 'Prioridad actualizada correctamente.');

        return $this->redirect('/admin/prioridades', 303);
    }

    /**
     * Activa o desactiva una prioridad mediante una acción explícita.
     */
    public function updateStatus(Request $request, string $priorityId): Response
    {
        $normalizedPriorityId = $this->validPriorityId($priorityId);
        $priority = $normalizedPriorityId === null
            ? null
            : $this->priorityModel()->findById($normalizedPriorityId);
        $submittedStatus = $request->input('activo');

        if ($priority === null || $normalizedPriorityId === null) {
            return $this->notFoundResponse();
        }

        if (!is_string($submittedStatus) || !in_array($submittedStatus, ['0', '1'], true)) {
            return $this->render(
                'errors/422',
                [
                    'title' => 'Estado no válido',
                    'message' => 'El estado indicado no es válido.',
                    'backUrl' => '/admin/prioridades',
                    'backLabel' => 'Volver a prioridades',
                ],
                422
            );
        }

        $priorityActive = $submittedStatus === '1';
        $this->priorityModel()->updateActiveStatus(
            $normalizedPriorityId,
            $priorityActive
        );
        Session::flash(
            'success',
            $priorityActive
                ? 'Prioridad activada correctamente.'
                : 'Prioridad desactivada correctamente.'
        );

        return $this->redirect('/admin/prioridades', 303);
    }

    /**
     * Obtiene y normaliza los valores editables enviados por el formulario.
     *
     * @return array{
     *     nombre: string,
     *     nivel: string,
     *     descripcion: string,
     *     color: string
     * }
     */
    private function priorityFormValues(Request $request): array
    {
        $submittedName = $request->input('nombre');
        $submittedLevel = $request->input('nivel');
        $submittedDescription = $request->input('descripcion');
        $submittedColor = $request->input('color');
        $normalizedColor = is_string($submittedColor)
            ? strtolower(trim($submittedColor))
            : '';

        return [
            'nombre' => is_string($submittedName) ? trim($submittedName) : '',
            'nivel' => is_string($submittedLevel) ? trim($submittedLevel) : '',
            'descripcion' => is_string($submittedDescription)
                ? trim($submittedDescription)
                : '',
            'color' => $normalizedColor,
        ];
    }

    /**
     * Valida los campos propios de una prioridad antes de consultar la base.
     *
     * @param array{
     *     nombre: string,
     *     nivel: string,
     *     descripcion: string,
     *     color: string
     * } $formValues
     *
     * @return array<string, string>
     */
    private function validatePriority(array $formValues): array
    {
        $validationErrors = [];

        if ($formValues['nombre'] === '') {
            $validationErrors['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($formValues['nombre']) > 50) {
            $validationErrors['nombre'] = 'El nombre no puede superar los 50 caracteres.';
        }

        if ($formValues['nivel'] === '') {
            $validationErrors['nivel'] = 'El nivel es obligatorio.';
        } elseif (
            !ctype_digit($formValues['nivel'])
            || (int) $formValues['nivel'] < 1
            || (int) $formValues['nivel'] > 255
        ) {
            $validationErrors['nivel'] = 'El nivel debe ser un entero entre 1 y 255.';
        }

        if (mb_strlen($formValues['descripcion']) > 255) {
            $validationErrors['descripcion'] = 'La descripción no puede superar los 255 caracteres.';
        }

        if (
            $formValues['color'] !== ''
            && preg_match('/^#[0-9a-f]{6}$/', $formValues['color']) !== 1
        ) {
            $validationErrors['color'] = 'El color debe usar el formato hexadecimal #RRGGBB.';
        }

        return $validationErrors;
    }

    /**
     * Agrega conflictos de nombre y nivel sólo cuando sus formatos son válidos.
     *
     * @param array{
     *     nombre: string,
     *     nivel: string,
     *     descripcion: string,
     *     color: string
     * } $formValues
     * @param array<string, string> $validationErrors
     *
     * @return array<string, string>
     */
    private function addUniquenessErrors(
        array $formValues,
        array $validationErrors,
        ?int $excludedPriorityId = null
    ): array {
        if (
            !isset($validationErrors['nombre'])
            && $this->priorityModel()->nameExists(
                $formValues['nombre'],
                $excludedPriorityId
            )
        ) {
            $validationErrors['nombre'] = 'Ya existe una prioridad con ese nombre.';
        }

        if (
            !isset($validationErrors['nivel'])
            && $this->priorityModel()->levelExists(
                (int) $formValues['nivel'],
                $excludedPriorityId
            )
        ) {
            $validationErrors['nivel'] = 'Ya existe una prioridad con ese nivel.';
        }

        return $validationErrors;
    }

    /**
     * Renderiza el formulario compartido de alta y edición.
     *
     * @param array{
     *     nombre: string,
     *     nivel: string,
     *     descripcion: string,
     *     color: string
     * } $formValues
     * @param array<string, string> $validationErrors
     */
    private function renderPriorityForm(
        string $heading,
        string $formAction,
        string $submitLabel,
        array $formValues,
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        return $this->render('prioridades/form', [
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
     * Convierte una cadena vacía al valor nulo utilizado por la base.
     */
    private function nullableValue(string $submittedValue): ?string
    {
        return $submittedValue === '' ? null : $submittedValue;
    }

    /**
     * Convierte un parámetro positivo a identificador de prioridad.
     */
    private function validPriorityId(string $priorityId): ?int
    {
        if (!ctype_digit($priorityId)) {
            return null;
        }

        $normalizedPriorityId = (int) $priorityId;

        return $normalizedPriorityId > 0 ? $normalizedPriorityId : null;
    }

    /**
     * Identifica conflictos de unicidad conservando otros errores de persistencia.
     */
    private function isUniqueConstraintViolation(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    /**
     * Renderiza la respuesta estándar cuando la prioridad no existe.
     */
    private function notFoundResponse(): Response
    {
        return $this->render(
            'errors/404',
            ['title' => 'Prioridad no encontrada'],
            404
        );
    }

    /**
     * Obtiene el modelo sin conectar la base en acciones que no consultan datos.
     */
    private function priorityModel(): Prioridad
    {
        if ($this->priorityModel === null) {
            $this->priorityModel = new Prioridad();
        }

        return $this->priorityModel;
    }
}
