<?php

declare(strict_types=1);

use App\Core\View;

?>
<h1>422</h1>
<p><?= View::escape($message) ?></p>
<p>
    <a href="<?= View::escape($backUrl ?? '/dashboard') ?>">
        <?= View::escape($backLabel ?? 'Volver al dashboard') ?>
    </a>
</p>
