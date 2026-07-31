<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Prioridades</h1>

<?php if (is_string($successMessage) && $successMessage !== ''): ?>
    <p role="status"><?= View::escape($successMessage) ?></p>
<?php endif; ?>

<p><a href="/admin/prioridades/crear">Nueva prioridad</a></p>

<?php if ($priorities === []): ?>
    <p>No hay prioridades registradas.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th scope="col">Nivel</th>
                <th scope="col">Nombre</th>
                <th scope="col">Descripción</th>
                <th scope="col">Color</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($priorities as $priority): ?>
                <tr>
                    <td><?= View::escape($priority['nivel']) ?></td>
                    <td><?= View::escape($priority['nombre']) ?></td>
                    <td><?= View::escape($priority['descripcion'] ?? 'Sin descripción') ?></td>
                    <td><?= View::escape($priority['color'] ?? 'Sin color') ?></td>
                    <td><?= $priority['activo'] ? 'Activa' : 'Inactiva' ?></td>
                    <td>
                        <a href="/admin/prioridades/<?= View::escape($priority['id']) ?>/editar">
                            Editar
                        </a>
                        <form
                            method="post"
                            action="/admin/prioridades/<?= View::escape($priority['id']) ?>/estado"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= View::escape($csrfToken) ?>"
                            >
                            <input
                                type="hidden"
                                name="activo"
                                value="<?= $priority['activo'] ? '0' : '1' ?>"
                            >
                            <button type="submit">
                                <?= $priority['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="/admin/configuraciones">Volver a configuraciones</a></p>
