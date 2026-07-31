<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Dashboard</h1>

<?php if (is_string($successMessage) && $successMessage !== ''): ?>
    <p role="status"><?= View::escape($successMessage) ?></p>
<?php endif; ?>

<p>
    Bienvenido,
    <?= View::escape($usuario['nombre']) ?>
    <?= View::escape($usuario['apellido']) ?>.
</p>
<p>Rol: <?= View::escape($usuario['rol']) ?></p>
<p>Los indicadores del dashboard se incorporarán en la Fase 9.</p>

<?php if ($usuario['rol'] === 'Administrador'): ?>
    <p><a href="/admin/configuraciones">Administrar configuraciones</a></p>
<?php endif; ?>

<form method="post" action="/logout">
    <input
        type="hidden"
        name="_token"
        value="<?= View::escape($csrfToken) ?>"
    >
    <button type="submit">Cerrar sesión</button>
</form>
