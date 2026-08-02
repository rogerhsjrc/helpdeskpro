<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Nuevo ticket</h1>

<?php if (isset($validationErrors['estado'])): ?>
    <p role="alert"><?= View::escape($validationErrors['estado']) ?></p>
<?php endif; ?>

<form method="post" action="/tickets">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">

    <div>
        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id" required>
            <option value="">Seleccione una categoría</option>
            <?php foreach ($categories as $category): ?>
                <option
                    value="<?= View::escape($category['id']) ?>"
                    <?= $formValues['categoria_id'] === (string) $category['id'] ? 'selected' : '' ?>
                >
                    <?= View::escape($category['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($validationErrors['categoria_id'])): ?>
            <p role="alert"><?= View::escape($validationErrors['categoria_id']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="prioridad_id">Prioridad</label>
        <select id="prioridad_id" name="prioridad_id" required>
            <option value="">Seleccione una prioridad</option>
            <?php foreach ($priorities as $priority): ?>
                <option
                    value="<?= View::escape($priority['id']) ?>"
                    <?= $formValues['prioridad_id'] === (string) $priority['id'] ? 'selected' : '' ?>
                >
                    <?= View::escape($priority['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($validationErrors['prioridad_id'])): ?>
            <p role="alert"><?= View::escape($validationErrors['prioridad_id']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="asunto">Asunto</label>
        <input
            id="asunto"
            name="asunto"
            type="text"
            maxlength="180"
            required
            value="<?= View::escape($formValues['asunto']) ?>"
        >
        <?php if (isset($validationErrors['asunto'])): ?>
            <p role="alert"><?= View::escape($validationErrors['asunto']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="descripcion">Descripción</label>
        <textarea
            id="descripcion"
            name="descripcion"
            maxlength="5000"
            required
        ><?= View::escape($formValues['descripcion']) ?></textarea>
        <?php if (isset($validationErrors['descripcion'])): ?>
            <p role="alert"><?= View::escape($validationErrors['descripcion']) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit">Crear ticket</button>
</form>

<p><a href="/tickets">Cancelar y volver a tickets</a></p>
