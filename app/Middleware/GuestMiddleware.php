<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

final class GuestMiddleware implements MiddlewareInterface
{
    /**
     * Reserva las rutas de invitado para usuarios no autenticados.
     */
    public function process(Request $request, Closure $next): Response
    {
        if (Session::authenticated()) {
            return Response::redirect('/dashboard');
        }

        return $next($request);
    }
}
