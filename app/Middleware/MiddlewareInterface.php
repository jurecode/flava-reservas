<?php
/**
 * Ruta: /app/Middleware/MiddlewareInterface.php
 */

namespace App\Middleware;

use Core\Request;
use Core\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request):Response $next
     */
    public function handle(Request $request, callable $next, ?string $argument = null): Response;
}
