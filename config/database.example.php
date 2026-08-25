<?php
/**
 * Ruta: /config/database.php
 * Credenciales de base de datos. Este archivo NO se versiona en producción
 * (ver .gitignore); usar /config/database.example.php como plantilla.
 */

return [
    'driver'    => 'mysql',
    'host'      => env('DB_HOST', 'localhost'),
    'port'      => env('DB_PORT', '3306'),
    'database'  => env('DB_DATABASE', 'flava_db'),
    'username'  => env('DB_USERNAME', 'root'),
    'password'  => env('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [],
];
