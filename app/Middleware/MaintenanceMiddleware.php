<?php
/**
 * Ruta: /app/Middleware/MaintenanceMiddleware.php
 *
 * Modo mantención durante actualizaciones (spec §127). El SUPER_ADMIN conserva
 * el acceso para poder terminar o revertir el despliegue.
 */

namespace App\Middleware;

use App\Services\System\MaintenanceService;
use Core\Auth;
use Core\Request;
use Core\Response;
use Core\View;

final class MaintenanceMiddleware implements MiddlewareInterface
{
    /**
     * Rutas que siguen funcionando durante la mantención: el SUPER_ADMIN debe
     * poder entrar para terminar o revertir el despliegue (spec §127).
     */
    private const ALLOWED_PREFIXES = ['/login', '/logout', '/super-admin', '/admin/system', '/cuenta'];

    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $maintenance = new MaintenanceService();

        if (!$maintenance->isEnabled()) {
            return $next($request);
        }

        if (Auth::isSuperAdmin()) {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($request->path(), $prefix)) {
                return $next($request);
            }
        }

        $data = $maintenance->info();

        if ($request->expectsJson()) {
            return Response::error($data['message'], [], 503);
        }

        return Response::make(View::render('errors.503', $data), 503)
            ->header('Retry-After', '600');
    }
}
