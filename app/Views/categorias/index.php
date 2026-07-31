<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Categorías</h1>

<?php if (is_string($successMessage) && $successMessage !== ''): ?>
    <p role="status"><?= View::escape($successMessage) ?></p>
<?php endif; ?>

<p><a href="/admin/categorias/crear">Nueva categoría</a></p>

<?php if ($categories === []): ?>
    <p>No hay categorías registradas.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th scope="col">Nombre</th>
                <th scope="col">Descripción</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= View::escape($category['nombre']) ?></td>
                    <td><?= View::escape($category['descripcion'] ?? 'Sin descripción') ?></td>
                    <td><?= $category['activo'] ? 'Activa' : 'Inactiva' ?></td>
                    <td>
                        <a href="/admin/categorias/<?= View::escape($category['id']) ?>/editar">
                            Editar
                        </a>
                        <form
                            method="post"
                            action="/admin/categorias/<?= View::escape($category['id']) ?>/estado"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= View::escape($csrfToken) ?>"
                            >
                            <input
                                type="hidden"
                                name="activo"
                                value="<?= $category['activo'] ? '0' : '1' ?>"
                            >
                            <button type="submit">
                                <?= $category['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="/admin/configuraciones">Volver a configuraciones</a></p>
