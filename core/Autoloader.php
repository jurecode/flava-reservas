<?php
/**
 * Ruta: /core/Autoloader.php
 * Autoload PSR-4 sin Composer (requisito: hosting tradicional sin SSH).
 */

namespace Core;

final class Autoloader
{
    /** @var array<string,string> prefijo de namespace => directorio base */
    private static array $prefixes = [];

    public static function register(array $prefixes = []): void
    {
        self::$prefixes = $prefixes;
        spl_autoload_register([self::class, 'load']);
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        self::$prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, '/');
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require_once $file;

                return;
            }
        }
    }
}
