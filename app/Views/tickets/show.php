<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1><?= View::escape($ticket['codigo']) ?></h1>
<h2><?= View::escape($ticket['asunto']) ?></h2>

<?php if (is_string($successMessage) && $successMessage !== ''): ?>
    <p role="status"><?= View::escape($successMessage) ?></p>
<?php endif; ?>

<?php if ($canEditOriginal): ?>
    <p>
        <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/editar">
            Editar contenido
        </a>
    </p>
<?php endif; ?>

<?php if ($usuario['rol'] === 'Administrador'): ?>
    <p>
        <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/asignar">
            <?= $ticket['tecnico'] === null ? 'Asignar técnico' : 'Reasignar técnico' ?>
        </a>
    </p>
<?php endif; ?>

<?php if (
    $usuario['rol'] === 'Administrador'
    || ($usuario['rol'] === 'Técnico' && $ticket['tecnico']['id'] === $usuario['id'])
): ?>
    <p>
        <a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/gestionar">
            Cambiar estado o prioridad
        </a>
    </p>
<?php endif; ?>

<dl>
    <dt>Estado</dt>
    <dd>
        <?= View::escape($ticket['estado']['nombre']) ?>
        <?php if (!$ticket['estado']['activo']): ?>(inactivo)<?php endif; ?>
    </dd>

    <dt>Prioridad</dt>
    <dd>
        <?= View::escape($ticket['prioridad']['nombre']) ?>
        <?php if (!$ticket['prioridad']['activa']): ?>(inactiva)<?php endif; ?>
    </dd>

    <dt>Categoría</dt>
    <dd>
        <?= View::escape($ticket['categoria']['nombre']) ?>
        <?php if (!$ticket['categoria']['activa']): ?>(inactiva)<?php endif; ?>
    </dd>

    <dt>Cliente</dt>
    <dd>
        <?= View::escape($ticket['cliente']['nombre']) ?>
        <?= View::escape($ticket['cliente']['apellido']) ?>
    </dd>

    <dt>Técnico</dt>
    <dd>
        <?php if ($ticket['tecnico'] === null): ?>
            Sin asignar
        <?php else: ?>
            <?= View::escape($ticket['tecnico']['nombre']) ?>
            <?= View::escape($ticket['tecnico']['apellido']) ?>
        <?php endif; ?>
    </dd>

    <dt>Creado</dt>
    <dd><?= View::escape($ticket['created_at']) ?></dd>
</dl>

<h2>Descripción</h2>
<p><?= nl2br(View::escape($ticket['descripcion'])) ?></p>

<p><a href="/tickets">Volver a tickets</a></p>
