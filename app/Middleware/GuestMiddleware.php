<?php
/**
 * Ruta: /app/Middleware/GuestMiddleware.php
 * Evita que un usuario autenticado vuelva a la pantalla de login.
 */

namespace App\Middleware;

use App\Support\Role;
use Core\Auth;
use Core\Request;
use Core\Response;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (Auth::check()) {
            return Response::redirect(Role::homeFor(Auth::role()));
        }

        return $next($request);
    }
}
