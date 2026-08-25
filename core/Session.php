<?php
/**
 * Ruta: /core/Session.php
 * Manejo de sesión, flash messages y token CSRF.
 */

namespace Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $config = config('app.session', []);

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($config['secure'] ?? true) && self::isHttps(),
            'httponly' => true,
            'samesite' => $config['same_site'] ?? 'Lax',
        ]);

        session_name($config['name'] ?? 'flava_session');

        // En hosting compartido el directorio de sesiones del sistema a veces no
        // es escribible. Antes de arrancar se comprueba, y si no sirve se usa
        // uno propio dentro de /storage: la alternativa es un 500 en la primera
        // página, sin ninguna pista de por qué.
        self::ensureWritableSavePath();

        // session_start() emite avisos cuando no puede escribir; se silencian
        // aquí para poder decidir qué hacer en vez de reventar la petición.
        if (!@session_start()) {
            logger()->error('No se pudo iniciar la sesión', [
                'save_path' => session_save_path(),
            ]);

            throw new \RuntimeException(
                'El servidor no puede iniciar sesiones. Revisa que la carpeta storage/ '
                . 'tenga permisos de escritura (755).'
            );
        }

        self::$started = true;

        self::enforceIdleTimeout((int) ($config['lifetime'] ?? 28800));
        self::rotateFlash();
    }

    /**
     * Garantiza un directorio de sesiones escribible.
     * Si el del sistema no lo es, se usa /storage/framework/sessions.
     */
    private static function ensureWritableSavePath(): void
    {
        $actual = session_save_path();

        if ($actual !== '' && is_dir($actual) && is_writable($actual)) {
            return;
        }

        $propio = STORAGE_PATH . '/framework/sessions';

        if (!is_dir($propio)) {
            @mkdir($propio, 0775, true);
        }

        if (is_dir($propio) && is_writable($propio)) {
            session_save_path($propio);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
    }

    private static function isHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    /** Cierra sesiones inactivas del personal interno. */
    private static function enforceIdleTimeout(int $lifetime): void
    {
        $last = $_SESSION['_last_activity'] ?? null;

        if ($last !== null && (time() - (int) $last) > $lifetime) {
            self::flush();
        }

        $_SESSION['_last_activity'] = time();
    }

    /**
     * Envejece los flash: se escriben con edad 0 y se eliminan en el arranque
     * siguiente a haber sido leídos/mostrados (edad >= 1).
     */
    private static function rotateFlash(): void
    {
        foreach (($_SESSION['_flash_age'] ?? []) as $key => $age) {
            if ($age >= 1) {
                unset($_SESSION['_flash'][$key], $_SESSION['_flash_age'][$key]);
                continue;
            }
            $_SESSION['_flash_age'][$key] = $age + 1;
        }

        if (!empty($_SESSION['_old_age'])) {
            unset($_SESSION['_old'], $_SESSION['_errors'], $_SESSION['_old_age']);
        } elseif (isset($_SESSION['_old']) || isset($_SESSION['_errors'])) {
            $_SESSION['_old_age'] = 1;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // ---- Flash ----

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key]     = $value;
        $_SESSION['_flash_age'][$key] = 0;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key], $_SESSION['_flash_age'][$key]);

        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /** Guarda input y errores para repoblar el formulario. */
    public static function flashInput(array $input, array $errors = []): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['token']);
        $_SESSION['_old']     = $input;
        $_SESSION['_errors']  = $errors;
        unset($_SESSION['_old_age']);
    }

    public static function clearInput(): void
    {
        unset($_SESSION['_old'], $_SESSION['_errors'], $_SESSION['_old_age']);
    }

    // ---- CSRF ----

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && $token !== ''
            && hash_equals(self::csrfToken(), $token);
    }
}
