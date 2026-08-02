<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Gestionar <?= View::escape($ticket['codigo']) ?></h1>

<form method="post" action="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/flujo">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">

    <label for="estado_id">Estado</label>
    <select id="estado_id" name="estado_id" required>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= View::escape($status['id']) ?>"
                <?= $selectedStatusId === (string) $status['id'] ? 'selected' : '' ?>>
                <?= View::escape($status['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($validationErrors['estado_id'])): ?>
        <p role="alert"><?= View::escape($validationErrors['estado_id']) ?></p>
    <?php endif; ?>

    <label for="prioridad_id">Prioridad</label>
    <select id="prioridad_id" name="prioridad_id" required>
        <?php foreach ($priorities as $priority): ?>
            <option value="<?= View::escape($priority['id']) ?>"
                <?= $selectedPriorityId === (string) $priority['id'] ? 'selected' : '' ?>>
                <?= View::escape($priority['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($validationErrors['prioridad_id'])): ?>
        <p role="alert"><?= View::escape($validationErrors['prioridad_id']) ?></p>
    <?php endif; ?>

    <button type="submit">Guardar flujo</button>
</form>

<p><a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>">Cancelar</a></p>
