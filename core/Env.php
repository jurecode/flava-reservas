<?php
/**
 * Ruta: /core/Env.php
 * Carga variables desde /.env y /config/secrets.php (fallback para hostings
 * que no permiten variables de entorno reales).
 */

namespace Core;

final class Env
{
    public static function load(string $basePath): void
    {
        self::loadDotEnv($basePath . '/.env');
        self::loadSecrets($basePath . '/config/secrets.php');
    }

    private static function loadDotEnv(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key           = trim($key);
            $value         = trim($value);

            if ($key === '') {
                continue;
            }

            // Quita comillas envolventes
            if (strlen($value) > 1 && (
                ($value[0] === '"' && str_ends_with($value, '"')) ||
                ($value[0] === "'" && str_ends_with($value, "'"))
            )) {
                $value = substr($value, 1, -1);
            }

            self::set($key, $value);
        }
    }

    private static function loadSecrets(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $secrets = require $file;

        if (!is_array($secrets)) {
            return;
        }

        foreach ($secrets as $key => $value) {
            if ($value !== '' && $value !== null) {
                self::set((string) $key, (string) $value);
            }
        }
    }

    public static function set(string $key, string $value): void
    {
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
