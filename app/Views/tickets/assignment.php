<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Asignar técnico a <?= View::escape($ticket['codigo']) ?></h1>

<p>
    Técnico actual:
    <?php if ($ticket['tecnico'] === null): ?>
        Sin asignar
    <?php else: ?>
        <?= View::escape($ticket['tecnico']['nombre']) ?>
        <?= View::escape($ticket['tecnico']['apellido']) ?>
    <?php endif; ?>
</p>

<form method="post" action="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/asignacion">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">

    <label for="tecnico_id">Técnico activo</label>
    <select id="tecnico_id" name="tecnico_id" required>
        <option value="">Seleccione un técnico</option>
        <?php foreach ($technicians as $technician): ?>
            <option value="<?= View::escape($technician['id']) ?>"
                <?= $selectedTechnicianId === (string) $technician['id'] ? 'selected' : '' ?>>
                <?= View::escape($technician['apellido']) ?>,
                <?= View::escape($technician['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if (is_string($validationError) && $validationError !== ''): ?>
        <p role="alert"><?= View::escape($validationError) ?></p>
    <?php endif; ?>

    <button type="submit">
        <?= $ticket['tecnico'] === null ? 'Asignar técnico' : 'Reasignar técnico' ?>
    </button>
</form>

<p><a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>">Cancelar</a></p>
