<?php
/**
 * Ruta: /app/Middleware/AuthMiddleware.php
 * Exige sesión iniciada del personal interno.
 */

namespace App\Middleware;

use Core\Auth;
use Core\Request;
use Core\Response;
use Core\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return Response::error('Debes iniciar sesión.', [], 401);
            }

            Session::put('_intended_url', $request->path());
            Session::flash('error', 'Inicia sesión para continuar.');

            return Response::redirect('/login');
        }

        // Cambio de contraseña obligatorio en el primer ingreso.
        $user = Auth::user();

        if (
            (int) ($user['must_change_password'] ?? 0) === 1
            && !in_array($request->path(), ['/cuenta/password', '/logout'], true)
        ) {
            if ($request->expectsJson()) {
                return Response::error('Debes cambiar tu contraseña antes de continuar.', [], 403);
            }

            return Response::redirect('/cuenta/password');
        }

        return $next($request);
    }
}
