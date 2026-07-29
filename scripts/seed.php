<?php

declare(strict_types=1);

use App\Core\Env;
use Database\Seeds\AdminSeeder;
use Database\Seeds\DemoSeeder;
use Database\Seeds\MasterDataSeeder;

require dirname(__DIR__) . '/vendor/autoload.php';

$projectRootPath = dirname(__DIR__);
Env::load($projectRootPath . '/.env');

try {
    (new MasterDataSeeder())->run();
    (new AdminSeeder())->run();

    if (in_array('--demo', $argv, true)) {
        (new DemoSeeder())->run();
    }
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Error al ejecutar los seeds: {$exception->getMessage()}\n"
    );

    exit(1);
}
