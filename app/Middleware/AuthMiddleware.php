<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

final class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Permite continuar sólo cuando existe un usuario autenticado.
     */
    public function process(Request $request, Closure $next): Response
    {
        if (!Session::authenticated()) {
            Session::flash(
                'error',
                'Debes iniciar sesión para acceder a esa página.'
            );

            return Response::redirect('/login');
        }

        return $next($request);
    }
}
