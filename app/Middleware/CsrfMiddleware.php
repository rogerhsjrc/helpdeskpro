<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Valida el token CSRF en solicitudes que pueden modificar datos.
     */
    public function process(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $submittedToken = $request->input('_token');

        if (
            !is_string($submittedToken)
            || !Session::validateCsrfToken($submittedToken)
        ) {
            return Response::html(
                '<h1>403</h1><p>La solicitud no pudo ser validada.</p>',
                403
            );
        }

        return $next($request);
    }
}
