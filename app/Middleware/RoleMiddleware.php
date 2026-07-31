<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use Closure;
use InvalidArgumentException;

final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private readonly array $allowedRoles;

    /**
     * Define los roles que pueden continuar hacia el recurso protegido.
     *
     * @param list<string> $allowedRoles
     *
     * @throws InvalidArgumentException Si no se configura al menos un rol válido.
     */
    public function __construct(array $allowedRoles)
    {
        $normalizedRoles = array_values(array_unique(array_map(
            static fn (mixed $roleName): string => is_string($roleName)
                ? trim($roleName)
                : '',
            $allowedRoles
        )));

        if ($normalizedRoles === [] || in_array('', $normalizedRoles, true)) {
            throw new InvalidArgumentException(
                'RoleMiddleware necesita al menos un nombre de rol válido.'
            );
        }

        $this->allowedRoles = $normalizedRoles;
    }

    /**
     * Autoriza la petición únicamente cuando el rol de sesión está permitido.
     */
    public function process(Request $request, Closure $next): Response
    {
        $authenticatedUser = Session::user();

        if ($authenticatedUser === null) {
            Session::flash(
                'error',
                'Debes iniciar sesión para acceder a esa página.'
            );

            return Response::redirect('/login');
        }

        if (!in_array($authenticatedUser['rol'], $this->allowedRoles, true)) {
            $view = new View(dirname(__DIR__) . '/Views');

            return Response::html(
                $view->render(
                    'errors/403',
                    ['title' => 'Acceso denegado'],
                    'layouts/app'
                ),
                403
            );
        }

        return $next($request);
    }
}
