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
        <?php if ($codeEditable): ?>
            <label for="codigo">Código interno</label>
            <input
                id="codigo"
                type="text"
                name="codigo"
                value="<?= View::escape($formValues['codigo']) ?>"
                minlength="3"
                maxlength="40"
                pattern="[A-Za-z][A-Za-z0-9_]{2,39}"
                required
                <?php if (isset($validationErrors['codigo'])): ?>
                    aria-invalid="true"
                    aria-describedby="codigo-error"
                <?php endif; ?>
            >
            <?php if (isset($validationErrors['codigo'])): ?>
                <p id="codigo-error"><?= View::escape($validationErrors['codigo']) ?></p>
            <?php endif; ?>
            <p>Identificador estable: después de crear el estado no podrá modificarse.</p>
        <?php else: ?>
            <p>
                <strong>Código interno:</strong>
                <code><?= View::escape($formValues['codigo']) ?></code>
            </p>
        <?php endif; ?>
    </div>

    <div>
        <label for="nombre">Nombre</label>
        <input
            id="nombre"
            type="text"
            name="nombre"
            value="<?= View::escape($formValues['nombre']) ?>"
            maxlength="60"
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
        <label for="orden">Orden</label>
        <input
            id="orden"
            type="number"
            name="orden"
            value="<?= View::escape($formValues['orden']) ?>"
            min="1"
            max="255"
            step="1"
            required
            <?php if (isset($validationErrors['orden'])): ?>
                aria-invalid="true"
                aria-describedby="orden-error"
            <?php endif; ?>
        >
        <?php if (isset($validationErrors['orden'])): ?>
            <p id="orden-error"><?= View::escape($validationErrors['orden']) ?></p>
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
        <input
            id="es_final"
            type="checkbox"
            name="es_final"
            value="1"
            <?= $formValues['es_final'] === '1' ? 'checked' : '' ?>
            <?php if (isset($validationErrors['es_final'])): ?>
                aria-invalid="true"
                aria-describedby="es-final-error"
            <?php endif; ?>
        >
        <label for="es_final">Es un estado final</label>
        <?php if (isset($validationErrors['es_final'])): ?>
            <p id="es-final-error"><?= View::escape($validationErrors['es_final']) ?></p>
        <?php endif; ?>
    </div>

    <button type="submit"><?= View::escape($submitLabel) ?></button>
    <a href="/admin/estados-ticket">Cancelar</a>
</form>
