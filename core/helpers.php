<?php
/**
 * Ruta: /core/helpers.php
 * Funciones globales de uso transversal. Cargadas por el bootstrap.
 */

if (!function_exists('env')) {
    /** Lee una variable de entorno / secreto con casting básico. */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty'            => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    /** config('app.name') — acceso con notación de punto y cache en memoria. */
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        $segments = explode('.', $key);
        $file     = array_shift($segments);

        if (!array_key_exists($file, $cache)) {
            $path         = CONFIG_PATH . '/' . $file . '.php';
            $cache[$file] = is_file($path) ? require $path : [];
        }

        $value = $cache[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('setting')) {
    /** Configuración editable desde el panel (tabla `settings`). */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Services\SettingService::get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escapa cualquier valor antes de imprimirlo en HTML (anti XSS). */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return $value ? '1' : '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('detect_base_url')) {
    /**
     * URL base deducida de la petición actual.
     *
     * Se usa cuando APP_URL todavía no está configurada: durante la instalación
     * el sistema aún no sabe en qué dominio vive, y redirigir al valor por
     * defecto dejaría al usuario fuera de su propio servidor.
     */
    function detect_base_url(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'http://localhost';
        }

        $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // El Host llega del cliente: sólo se aceptan caracteres de un dominio.
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
            $host = 'localhost';
        }

        return ($https ? 'https://' : 'http://') . $host;
    }
}

if (!function_exists('url')) {
    /** URL absoluta dentro de la app. */
    function url(string $path = '/'): string
    {
        return config('app.url') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('public_prefix')) {
    /**
     * Prefijo bajo el que se sirven los archivos públicos.
     *
     * Hay dos formas de desplegar:
     *   · El dominio apunta a /public → los assets viven en la raíz de la URL.
     *   · El dominio apunta a la raíz del proyecto (hosting compartido sin
     *     opción de cambiar el directorio raíz) → los assets están en /public.
     *
     * Cada front controller declara cuál es su caso con FLAVA_ENTRY, así los
     * enlaces salen correctos con o sin reescritura de URLs.
     */
    function public_prefix(): string
    {
        return (defined('FLAVA_ENTRY') && FLAVA_ENTRY === 'root') ? 'public/' : '';
    }
}

if (!function_exists('asset')) {
    /** URL de un asset público con cache-busting por versión. */
    function asset(string $path): string
    {
        $version = config('version.version', '1.0.0');

        return url(public_prefix() . 'assets/' . ltrim($path, '/')) . '?v=' . $version;
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $path, ?string $fallback = null): ?string
    {
        if (!$path) {
            return $fallback;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return url(public_prefix() . 'uploads/' . ltrim($path, '/'));
    }
}

if (!function_exists('route_is')) {
    /** ¿La URI actual coincide con el patrón dado? Útil para menús activos. */
    function route_is(string $pattern): bool
    {
        $uri = '/' . trim(\Core\Request::current()?->path() ?? '', '/');

        return (bool) preg_match('#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#', $uri);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \Core\Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /** Valor previo de un formulario tras un error de validación. */
    function old(string $key, mixed $default = ''): mixed
    {
        $data = \Core\Session::get('_old', []);

        return $data[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return \Core\Session::get('_errors', []);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        $errors = errors();

        return isset($errors[$field]) ? (is_array($errors[$field]) ? $errors[$field][0] : $errors[$field]) : null;
    }
}

if (!function_exists('icon')) {
    /** Ícono SVG en línea. Reemplaza los emoji en toda la interfaz. */
    function icon(string $name, int $size = 18, ?string $class = null): string
    {
        return \App\Support\Icon::render($name, $size, $class);
    }
}

if (!function_exists('money')) {
    /** 15000 -> $15.000 (formato CLP). */
    function money(int|float|string|null $amount, bool $withSymbol = true): string
    {
        return \App\Support\Money::format($amount, $withSymbol);
    }
}

if (!function_exists('now')) {
    function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'America/Santiago')));
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return now()->format('Y-m-d');
    }
}

if (!function_exists('date_es')) {
    /** "viernes 28 de agosto" / "28 de agosto de 2026" */
    function date_es(string $date, bool $withYear = false, bool $withWeekday = true): string
    {
        return \App\Support\DateHelper::longEs($date, $withYear, $withWeekday);
    }
}

if (!function_exists('time_hm')) {
    function time_hm(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '';
    }
}

if (!function_exists('logger')) {
    function logger(): \Core\Logger
    {
        return \Core\Logger::instance();
    }
}

if (!function_exists('str_random')) {
    function str_random(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');

        return trim($text, '-') ?: 'item';
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        if (PHP_SAPI !== 'cli') {
            echo '<pre style="background:#0D0D0D;color:#FFC400;padding:16px;overflow:auto">';
        }
        foreach ($values as $value) {
            var_dump($value);
        }
        exit(1);
    }
}
