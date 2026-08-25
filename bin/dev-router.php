<?php
/**
 * Ruta: /bin/dev-router.php
 * Router para el servidor embebido de PHP (sólo desarrollo local):
 *
 *   php -S localhost:8080 -t public bin/dev-router.php
 *
 * En producción esto no se usa: Apache + .htaccess hacen el trabajo.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;

// Los archivos reales (css, js, imágenes) los sirve el servidor directamente.
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';
