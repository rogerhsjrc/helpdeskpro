<?php

declare(strict_types=1);

use App\Core\View;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= View::escape($title ?? 'HelpDesk Pro') ?></title>
</head>
<body>
    <header>
        <a href="/">HelpDesk Pro</a>
    </header>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
