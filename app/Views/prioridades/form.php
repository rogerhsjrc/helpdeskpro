<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1><?= View::escape($heading) ?></h1>

<?php if ($validationErrors !== []): ?>
    <div role="alert">
        <p>Revisa los datos ingresados.</p>
        <ul>
            <?php foreach ($validationErrors as $validationError): ?>
                <li><?= View::escape($validationError) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= View::escape($formAction) ?>">
    <input
        type="hidden"
        name="_token"
        value="<?= View::escape($csrfToken) ?>"
    >

    <div>
        <label for="nombre">Nombre</label>
        <input
            id="nombre"
            type="text"
            name="nombre"
            value="<?= View::escape($formValues['nombre']) ?>"
            maxlength="50"
            required
            autofocus
            <?php if (isset($validationErrors['nombre'])): ?>
                aria-invalid="true"
                aria-describedby="nombre-error"
            <?php endif; ?>
        >
        <?php if (isset($validationErrors['nombre'])): ?>
            <p id="nombre-error"><?= View::escape($validationErrors['nombre']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="nivel">Nivel</label>
        <input
            id="nivel"
            type="number"
            name="nivel"
            value="<?= View::escape($formValues['nivel']) ?>"
            min="1"
            max="255"
            step="1"
            required
            <?php if (isset($validationErrors['nivel'])): ?>
                aria-invalid="true"
                aria-describedby="nivel-error"
            <?php endif; ?>
        >
        <?php if (isset($validationErrors['nivel'])): ?>
            <p id="nivel-error"><?= View::escape($validationErrors['nivel']) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label for="descripcion">Descripción</label>
        <textarea
            id="descripcion"
            name="descripcion"
            maxlength="255"
            rows="4"
            <?php if (isset($validationErrors['descripcion'])): ?>
                aria-invalid="true"
                aria-describedby="descripcion-error"
            <?php endif; ?>
        ><?= View::escape($formValues['descripcion']) ?></textarea>
        <?php if (isset($validationErrors['descripcion'])): ?>
            <p id="descripcion-error">
                <?= View::escape($validationErrors['descripcion']) ?>
            </p>
        <?php endif; ?>
    </div>

    <div>
        <label for="color">Color hexadecimal</label>
        <input
            id="color"
            type="text"
            name="color"
            value="<?= View::escape($formValues['color']) ?>"
            maxlength="7"
            pattern="#[0-9a-fA-F]{6}"
            placeholder="#0d6efd"
            <?php if (isset($validationErrors['color'])): ?>
                aria-invalid="true"
                aria-describedby="color-error"
            <?php endif; ?>
        >
        <?php if (isset($validationErrors['color'])): ?>
            <p id="color-error"><?= View::escape($validationErrors['color']) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit"><?= View::escape($submitLabel) ?></button>
    <a href="/admin/prioridades">Cancelar</a>
</form>
