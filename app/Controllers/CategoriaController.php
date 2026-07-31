<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Categoria;
use PDOException;

final class CategoriaController extends Controller
{
    private ?Categoria $categoryModel;

    /**
     * Permite proporcionar un modelo controlado para pruebas aisladas.
     */
    public function __construct(?Categoria $categoryModel = null)
    {
        $this->categoryModel = $categoryModel;
    }

    /**
     * Muestra todas las categorías y las acciones administrativas disponibles.
     */
    public function index(Request $request): Response
    {
        return $this->render('categorias/index', [
            'title' => 'Categorías | HelpDesk Pro',
            'categories' => $this->categoryModel()->all(),
            'csrfToken' => Session::csrfToken(),
            'successMessage' => Session::pullFlash('success'),
        ]);
    }

    /**
     * Muestra el formulario vacío para registrar una categoría.
     */
    public function create(Request $request): Response
    {
        return $this->renderCategoryForm(
            'Nueva categoría',
            '/admin/categorias',
            'Crear categoría',
            ['nombre' => '', 'descripcion' => '']
        );
    }

    /**
     * Valida y registra una nueva categoría.
     */
    public function store(Request $request): Response
    {
        $formValues = $this->categoryFormValues($request);
        $validationErrors = $this->validateCategory($formValues);

        if (
            !isset($validationErrors['nombre'])
            && $this->categoryModel()->nameExists($formValues['nombre'])
        ) {
            $validationErrors['nombre'] = 'Ya existe una categoría con ese nombre.';
        }

        if ($validationErrors !== []) {
            return $this->renderCategoryForm(
                'Nueva categoría',
                '/admin/categorias',
                'Crear categoría',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->categoryModel()->create(
                $formValues['nombre'],
                $this->nullableDescription($formValues['descripcion'])
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderCategoryForm(
                'Nueva categoría',
                '/admin/categorias',
                'Crear categoría',
                $formValues,
                ['nombre' => 'Ya existe una categoría con ese nombre.'],
                422
            );
        }

        Session::flash('success', 'Categoría creada correctamente.');

        return $this->redirect('/admin/categorias', 303);
    }

    /**
     * Muestra el formulario de una categoría existente o una respuesta 404.
     */
    public function edit(Request $request, string $categoryId): Response
    {
        $normalizedCategoryId = $this->validCategoryId($categoryId);
        $category = $normalizedCategoryId === null
            ? null
            : $this->categoryModel()->findById($normalizedCategoryId);

        if ($category === null) {
            return $this->notFoundResponse();
        }

        return $this->renderCategoryForm(
            'Editar categoría',
            sprintf('/admin/categorias/%d/actualizar', $category['id']),
            'Guardar cambios',
            [
                'nombre' => $category['nombre'],
                'descripcion' => $category['descripcion'] ?? '',
            ]
        );
    }

    /**
     * Valida y actualiza una categoría existente.
     */
    public function update(Request $request, string $categoryId): Response
    {
        $normalizedCategoryId = $this->validCategoryId($categoryId);
        $category = $normalizedCategoryId === null
            ? null
            : $this->categoryModel()->findById($normalizedCategoryId);

        if ($category === null || $normalizedCategoryId === null) {
            return $this->notFoundResponse();
        }

        $formValues = $this->categoryFormValues($request);
        $validationErrors = $this->validateCategory($formValues);

        if (
            !isset($validationErrors['nombre'])
            && $this->categoryModel()->nameExists(
                $formValues['nombre'],
                $normalizedCategoryId
            )
        ) {
            $validationErrors['nombre'] = 'Ya existe una categoría con ese nombre.';
        }

        $formAction = sprintf(
            '/admin/categorias/%d/actualizar',
            $normalizedCategoryId
        );

        if ($validationErrors !== []) {
            return $this->renderCategoryForm(
                'Editar categoría',
                $formAction,
                'Guardar cambios',
                $formValues,
                $validationErrors,
                422
            );
        }

        try {
            $this->categoryModel()->update(
                $normalizedCategoryId,
                $formValues['nombre'],
                $this->nullableDescription($formValues['descripcion'])
            );
        } catch (PDOException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return $this->renderCategoryForm(
                'Editar categoría',
                $formAction,
                'Guardar cambios',
                $formValues,
                ['nombre' => 'Ya existe una categoría con ese nombre.'],
                422
            );
        }

        Session::flash('success', 'Categoría actualizada correctamente.');

        return $this->redirect('/admin/categorias', 303);
    }

    /**
     * Activa o desactiva una categoría existente mediante una acción explícita.
     */
    public function updateStatus(Request $request, string $categoryId): Response
    {
        $normalizedCategoryId = $this->validCategoryId($categoryId);
        $category = $normalizedCategoryId === null
            ? null
            : $this->categoryModel()->findById($normalizedCategoryId);
        $submittedStatus = $request->input('activo');

        if ($category === null || $normalizedCategoryId === null) {
            return $this->notFoundResponse();
        }

        if (!is_string($submittedStatus) || !in_array($submittedStatus, ['0', '1'], true)) {
            return $this->render(
                'errors/422',
                [
                    'title' => 'Estado no válido',
                    'message' => 'El estado indicado no es válido.',
                    'backUrl' => '/admin/categorias',
                    'backLabel' => 'Volver a categorías',
                ],
                422
            );
        }

        $categoryActive = $submittedStatus === '1';
        $this->categoryModel()->updateActiveStatus(
            $normalizedCategoryId,
            $categoryActive
        );
        Session::flash(
            'success',
            $categoryActive
                ? 'Categoría activada correctamente.'
                : 'Categoría desactivada correctamente.'
        );

        return $this->redirect('/admin/categorias', 303);
    }

    /**
     * Obtiene y normaliza los valores editables enviados por el formulario.
     *
     * @return array{nombre: string, descripcion: string}
     */
    private function categoryFormValues(Request $request): array
    {
        $submittedName = $request->input('nombre');
        $submittedDescription = $request->input('descripcion');

        return [
            'nombre' => is_string($submittedName) ? trim($submittedName) : '',
            'descripcion' => is_string($submittedDescription)
                ? trim($submittedDescription)
                : '',
        ];
    }

    /**
     * Valida las reglas de entrada de una categoría antes de consultar o persistir.
     *
     * @param array{nombre: string, descripcion: string} $formValues
     *
     * @return array<string, string>
     */
    private function validateCategory(array $formValues): array
    {
        $validationErrors = [];

        if ($formValues['nombre'] === '') {
            $validationErrors['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($formValues['nombre']) > 80) {
            $validationErrors['nombre'] = 'El nombre no puede superar los 80 caracteres.';
        }

        if (mb_strlen($formValues['descripcion']) > 255) {
            $validationErrors['descripcion'] = 'La descripción no puede superar los 255 caracteres.';
        }

        return $validationErrors;
    }

    /**
     * Renderiza el formulario compartido de alta y edición.
     *
     * @param array{nombre: string, descripcion: string} $formValues
     * @param array<string, string> $validationErrors
     */
    private function renderCategoryForm(
        string $heading,
        string $formAction,
        string $submitLabel,
        array $formValues,
        array $validationErrors = [],
        int $statusCode = 200
    ): Response {
        return $this->render('categorias/form', [
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
    private function nullableDescription(string $categoryDescription): ?string
    {
        return $categoryDescription === '' ? null : $categoryDescription;
    }

    /**
     * Convierte un parámetro de ruta positivo a identificador de categoría.
     */
    private function validCategoryId(string $categoryId): ?int
    {
        if (!ctype_digit($categoryId)) {
            return null;
        }

        $normalizedCategoryId = (int) $categoryId;

        return $normalizedCategoryId > 0 ? $normalizedCategoryId : null;
    }

    /**
     * Identifica conflictos de unicidad conservando otros errores de persistencia.
     */
    private function isUniqueConstraintViolation(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    /**
     * Renderiza la respuesta estándar cuando la categoría solicitada no existe.
     */
    private function notFoundResponse(): Response
    {
        return $this->render(
            'errors/404',
            ['title' => 'Categoría no encontrada'],
            404
        );
    }

    /**
     * Obtiene el modelo sin abrir una conexión en acciones que no consultan datos.
     */
    private function categoryModel(): Categoria
    {
        if ($this->categoryModel === null) {
            $this->categoryModel = new Categoria();
        }

        return $this->categoryModel;
    }
}
