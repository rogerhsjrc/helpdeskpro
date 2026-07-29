<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

interface MiddlewareInterface
{
    /**
     * Procesa la petición y decide si continúa con el siguiente elemento.
     */
    public function process(Request $request, Closure $next): Response;
}
