<?php
/**
 * Ruta: /app/Middleware/InstallMiddleware.php
 *
 * Dos funciones opuestas y complementarias:
 *   · Si el sistema NO está instalado, cualquier ruta lleva al asistente.
 *   · Si YA está instalado, el asistente deja de existir (404), para que nadie
 *     pueda relanzarlo sobre una instalación con datos reales.
 */

namespace App\Middleware;

use App\Services\System\InstallerService;
use Core\Exceptions\HttpException;
use Core\Request;
use Core\Response;

final class InstallMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $installed = (new InstallerService())->isInstalled();
        $onWizard  = str_starts_with($request->path(), '/instalar');

        if ($installed && $onWizard) {
            throw HttpException::notFound('El sistema ya está instalado.');
        }

        if (!$installed && !$onWizard) {
            if ($request->expectsJson()) {
                return Response::error('El sistema todavía no está instalado.', [], 503);
            }

            return Response::redirect('/instalar');
        }

        return $next($request);
    }
}
