<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Editar <?= View::escape($ticket['codigo']) ?></h1>

<p>La prioridad, el estado y la asignación se gestionan mediante acciones independientes.</p>

<form method="post" action="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>/actualizar">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">

    <div>
        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= View::escape($category['id']) ?>"
                    <?= $formValues['categoria_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                    <?= View::escape($category['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($validationErrors['categoria_id'])): ?>
            <p role="alert"><?= View::escape($validationErrors['categoria_id']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="asunto">Asunto</label>
        <input id="asunto" name="asunto" type="text" maxlength="180" required
            value="<?= View::escape($formValues['asunto']) ?>">
        <?php if (isset($validationErrors['asunto'])): ?>
            <p role="alert"><?= View::escape($validationErrors['asunto']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" maxlength="5000" required><?= View::escape($formValues['descripcion']) ?></textarea>
        <?php if (isset($validationErrors['descripcion'])): ?>
            <p role="alert"><?= View::escape($validationErrors['descripcion']) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit">Guardar cambios</button>
</form>

<p><a href="/tickets/<?= View::escape(rawurlencode($ticket['codigo'])) ?>">Cancelar</a></p>
