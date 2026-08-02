<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Tickets</h1>

<?php if ($usuario['rol'] === 'Cliente'): ?>
    <p><a href="/tickets/crear">Nuevo ticket</a></p>
<?php endif; ?>

<form action="/tickets" method="get">
    <fieldset>
        <legend>Filtrar tickets</legend>

        <div>
            <label for="busqueda">Código o asunto</label>
            <input
                id="busqueda"
                name="busqueda"
                type="search"
                maxlength="100"
                value="<?= View::escape($filterValues['busqueda']) ?>"
            >
        </div>

        <div>
            <label for="estado_id">Estado</label>
            <select id="estado_id" name="estado_id">
                <option value="">Todos los estados</option>
                <?php foreach ($statuses as $status): ?>
                    <option
                        value="<?= View::escape($status['id']) ?>"
                        <?= $filterValues['estado_id'] === (string) $status['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= View::escape($status['nombre']) ?>
                        <?= $status['activo'] ? '' : ' (inactivo)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="prioridad_id">Prioridad</label>
            <select id="prioridad_id" name="prioridad_id">
                <option value="">Todas las prioridades</option>
                <?php foreach ($priorities as $priority): ?>
                    <option
                        value="<?= View::escape($priority['id']) ?>"
                        <?= $filterValues['prioridad_id'] === (string) $priority['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= View::escape($priority['nombre']) ?>
                        <?= $priority['activo'] ? '' : ' (inactiva)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="tecnico_id">Técnico</label>
            <select id="tecnico_id" name="tecnico_id">
                <option value="">Todos los técnicos</option>
                <?php foreach ($technicians as $technician): ?>
                    <option
                        value="<?= View::escape($technician['id']) ?>"
                        <?= $filterValues['tecnico_id'] === (string) $technician['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= View::escape($technician['apellido']) ?>,
                        <?= View::escape($technician['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit">Aplicar filtros</button>
        <?php if ($hasActiveFilters): ?>
            <a href="/tickets">Limpiar filtros</a>
        <?php endif; ?>
    </fieldset>
</form>

<?php if ($tickets === []): ?>
    <p>
        <?= $hasActiveFilters
            ? 'No hay tickets que coincidan con los filtros.'
            : 'No hay tickets disponibles.' ?>
    </p>
<?php else: ?>
    <p>
        <?= View::escape($pagination['total']) ?> ticket(s) disponible(s).
    </p>
    <table>
        <thead>
            <tr>
                <th scope="col">Código</th>
                <th scope="col">Asunto</th>
                <th scope="col">Estado</th>
                <th scope="col">Prioridad</th>
                <th scope="col">Cliente</th>
                <th scope="col">Técnico</th>
                <th scope="col">Creado</th>
                <th scope="col">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td>
                        <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>">
                            <?= View::escape($ticket['codigo']) ?>
                        </a>
                    </td>
                    <td><?= View::escape($ticket['asunto']) ?></td>
                    <td>
                        <?= View::escape($ticket['estado']['nombre']) ?>
                        <?php if (!$ticket['estado']['activo']): ?>
                            (inactivo)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= View::escape($ticket['prioridad']['nombre']) ?>
                        <?php if (!$ticket['prioridad']['activa']): ?>
                            (inactiva)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= View::escape($ticket['cliente']['nombre']) ?>
                        <?= View::escape($ticket['cliente']['apellido']) ?>
                    </td>
                    <td>
                        <?php if ($ticket['tecnico'] === null): ?>
                            Sin asignar
                        <?php else: ?>
                            <?= View::escape($ticket['tecnico']['nombre']) ?>
                            <?= View::escape($ticket['tecnico']['apellido']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= View::escape($ticket['created_at']) ?></td>
                    <td>
                        <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>">
                            Ver detalle
                        </a>

                        <?php if ($ticket['acciones']['editar']): ?>
                            <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/editar">
                                Editar
                            </a>
                        <?php endif; ?>

                        <?php if ($ticket['acciones']['asignar']): ?>
                            <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/asignar">
                                <?= $ticket['tecnico'] === null ? 'Asignar' : 'Reasignar' ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($ticket['acciones']['gestionar']): ?>
                            <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/gestionar">
                                Gestionar flujo
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($pagination['ultima_pagina'] > 1): ?>
        <nav aria-label="Paginación de tickets">
            <?php if ($pagination['pagina_actual'] > 1): ?>
                <a href="<?= View::escape($previousPageUrl) ?>">
                    Anterior
                </a>
            <?php endif; ?>

            <span>
                Página <?= View::escape($pagination['pagina_actual']) ?>
                de <?= View::escape($pagination['ultima_pagina']) ?>
            </span>

            <?php if ($pagination['pagina_actual'] < $pagination['ultima_pagina']): ?>
                <a href="<?= View::escape($nextPageUrl) ?>">
                    Siguiente
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<p><a href="/dashboard">Volver al dashboard</a></p>
