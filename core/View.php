<?php
/**
 * Ruta: /core/View.php
 * Renderizador de vistas PHP con layouts y secciones.
 * Las vistas SOLO reciben datos ya procesados por el controlador.
 */

namespace Core;

final class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $stack = [];

    /** Variables que la vista envía a su layout (título, paso, clase del body...). */
    private static array $forLayout = [];

    /** Datos disponibles en todas las vistas (nombre de la app, usuario, etc.). */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /**
     * Envía una variable de la vista al layout.
     * Las variables locales de una vista no son visibles para el layout, que se
     * renderiza en un ámbito propio: esto es el puente explícito entre ambos.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$forLayout[$key] = $value;
    }

    public static function setMany(array $values): void
    {
        self::$forLayout = array_merge(self::$forLayout, $values);
    }

    /**
     * Renderiza una vista. $view usa notación de punto: 'booking.service'.
     * Si $layout es null se usa el definido con `@layout` vía View::layout().
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $output   = self::capture($view, $data);
        $layout ??= self::$sections['__layout'] ?? null;

        if ($layout !== null) {
            // La vista puede definir su contenido con View::start('content'),
            // o simplemente imprimirlo: ambos casos quedan cubiertos.
            if (trim(self::$sections['content'] ?? '') === '') {
                self::$sections['content'] = $output;
            }

            $output = self::capture('layouts.' . $layout, array_merge($data, self::$forLayout));
        }

        self::$sections  = [];
        self::$forLayout = [];

        return $output;
    }

    /** Renderiza una vista parcial sin layout (componentes, emails, fragmentos AJAX). */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    /**
     * Renderiza un archivo de vista capturando su salida.
     *
     * Las variables locales usan el prefijo `__flava` a propósito: `extract()`
     * vuelca datos del controlador en este ámbito y un dato llamado `$path` o
     * `$view` llegaría a sobrescribir el estado del propio renderizador.
     */
    private static function capture(string $__flavaView, array $__flavaData): string
    {
        $__flavaPath = self::path($__flavaView);

        if (!is_file($__flavaPath)) {
            throw new \RuntimeException("Vista no encontrada: {$__flavaView} ({$__flavaPath})");
        }

        extract(self::$shared, EXTR_SKIP);
        extract($__flavaData, EXTR_OVERWRITE);

        // Se recuperan tras el extract por si un dato usara esos nombres.
        $__flavaPath = self::path($__flavaView);

        ob_start();

        try {
            require $__flavaPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public static function path(string $view): string
    {
        return APP_PATH . '/Views/' . str_replace('.', '/', trim($view, '.')) . '.php';
    }

    public static function exists(string $view): bool
    {
        return is_file(self::path($view));
    }

    // ---- Secciones ----

    /** Declara el layout que envolverá la vista actual. */
    public static function layout(string $layout): void
    {
        self::$sections['__layout'] = $layout;
    }

    public static function start(string $section): void
    {
        self::$stack[] = $section;
        ob_start();
    }

    public static function stop(): void
    {
        $section = array_pop(self::$stack);

        if ($section !== null) {
            self::$sections[$section] = (string) ob_get_clean();
        }
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return !empty(self::$sections[$name]);
    }
}
