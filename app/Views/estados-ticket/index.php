<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Estados de ticket</h1>

<?php if (is_string($successMessage) && $successMessage !== ''): ?>
    <p role="status"><?= View::escape($successMessage) ?></p>
<?php endif; ?>

<p><a href="/admin/estados-ticket/crear">Nuevo estado</a></p>

<?php if ($ticketStatuses === []): ?>
    <p>No hay estados de ticket registrados.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Orden</th>
                <th scope="col">Nombre</th>
                <th scope="col">Descripción</th>
                <th scope="col">Final</th>
                <th scope="col">Estado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ticketStatuses as $ticketStatus): ?>
                <tr>
                    <td><?= View::escape($ticketStatus['codigo']) ?></td>
                    <td><?= View::escape($ticketStatus['orden']) ?></td>
                    <td><?= View::escape($ticketStatus['nombre']) ?></td>
                    <td><?= View::escape($ticketStatus['descripcion'] ?? 'Sin descripción') ?></td>
                    <td><?= $ticketStatus['es_final'] ? 'Sí' : 'No' ?></td>
                    <td><?= $ticketStatus['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <a href="/admin/estados-ticket/<?= View::escape($ticketStatus['id']) ?>/editar">
                            Editar
                        </a>
                        <form
                            method="post"
                            action="/admin/estados-ticket/<?= View::escape($ticketStatus['id']) ?>/estado"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value="<?= View::escape($csrfToken) ?>"
                            >
                            <input
                                type="hidden"
                                name="activo"
                                value="<?= $ticketStatus['activo'] ? '0' : '1' ?>"
                            >
                            <button type="submit">
                                <?= $ticketStatus['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p><a href="/admin/configuraciones">Volver a configuraciones</a></p>
