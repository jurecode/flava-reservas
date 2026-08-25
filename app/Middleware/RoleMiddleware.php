<?php
/**
 * Ruta: /app/Middleware/RoleMiddleware.php
 *
 * Autorización por rol en el BACKEND (spec §54): no basta con ocultar botones.
 * Uso en rutas:  'role:ADMIN|SUPER_ADMIN'  ·  'role:BARBER'
 */

namespace App\Middleware;

use App\Services\ActivityLogger;
use App\Support\Role;
use Core\Auth;
use Core\Request;
use Core\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $allowed = array_filter(array_map('trim', explode('|', (string) $argument)));

        if ($allowed === []) {
            return $next($request);
        }

        // El SUPER_ADMIN alcanza cualquier área del sistema.
        if (Auth::isSuperAdmin() || Auth::hasRole(...$allowed)) {
            return $next($request);
        }

        ActivityLogger::log(
            'auth.forbidden',
            'route',
            null,
            'Intento de acceso a ' . $request->path() . ' con rol ' . Role::label(Auth::role())
        );

        if ($request->expectsJson()) {
            return Response::error('No tienes permisos para esta acción.', [], 403);
        }

        throw \Core\Exceptions\HttpException::forbidden();
    }
}
