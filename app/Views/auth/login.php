<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>Iniciar sesión</h1>

<?php if (is_string($errorMessage) && $errorMessage !== ''): ?>
    <p role="alert"><?= View::escape($errorMessage) ?></p>
<?php endif; ?>

<form method="post" action="/login">
    <input
        type="hidden"
        name="_token"
        value="<?= View::escape($csrfToken) ?>"
    >

    <div>
        <label for="email">Correo electrónico</label>
        <input
            id="email"
            type="email"
            name="email"
            value="<?= View::escape($email) ?>"
            maxlength="150"
            autocomplete="username"
            required
            autofocus
        >
    </div>

    <div>
        <label for="password">Contraseña</label>
        <input
            id="password"
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >
    </div>

    <button type="submit">Ingresar</button>
</form>
