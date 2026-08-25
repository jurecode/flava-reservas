<?php
/**
 * Ruta: /app/Middleware/CsrfMiddleware.php
 * Protección CSRF de todos los métodos que modifican estado (spec §55).
 */

namespace App\Middleware;

use Core\Exceptions\HttpException;
use Core\Request;
use Core\Response;
use Core\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (!in_array($request->method(), self::PROTECTED_METHODS, true)) {
            return $next($request);
        }

        $token = $request->input('_token')
            ?? $request->header('X-CSRF-Token')
            ?? $request->header('X-Csrf-Token');

        if (!Session::verifyCsrf(is_string($token) ? $token : null)) {
            logger()->warning('Token CSRF inválido', [
                'uri' => $request->path(),
                'ip'  => $request->ip(),
            ]);

            if ($request->expectsJson()) {
                return Response::error('La sesión expiró. Recarga la página e inténtalo de nuevo.', [], 419);
            }

            throw HttpException::csrf();
        }

        return $next($request);
    }
}
