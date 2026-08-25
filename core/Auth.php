<?php
/**
 * Ruta: /core/Auth.php
 * Autenticación del personal interno (SUPER_ADMIN, ADMIN, RECEPTION, BARBER).
 * Los clientes NO son usuarios: reservan como invitados (spec §10).
 */

namespace Core;

use App\Models\User;
use App\Support\Role;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';
    private const CONFIRM_KEY = '_auth_confirmed_at';

    private static ?array $cachedUser = null;

    /** Intento de login con control de fuerza bruta. */
    public static function attempt(string $email, string $password, string $ip = ''): bool
    {
        if (self::isLocked($email)) {
            return false;
        }

        $user = (new User())->findByEmail($email);

        if ($user === null || (int) $user['status'] !== 1 || !password_verify($password, (string) $user['password'])) {
            self::recordFailure($email);
            usleep(random_int(150_000, 400_000)); // ralentiza ataques automatizados

            return false;
        }

        // Rehash si el algoritmo por defecto cambió.
        if (password_needs_rehash((string) $user['password'], PASSWORD_DEFAULT)) {
            (new User())->updatePassword((int) $user['id'], $password);
        }

        self::login($user);
        self::clearFailures($email);

        return true;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put(self::CONFIRM_KEY, time());
        self::$cachedUser = null;

        (new User())->touchLastLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY, self::CONFIRM_KEY);
        Session::destroy();
        self::$cachedUser = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);

        return $id === null ? null : (int) $id;
    }

    /** Usuario autenticado (sin password ni tokens). */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $id = self::id();
        if ($id === null) {
            return null;
        }

        $model = new User();
        $user  = $model->find($id);

        if ($user === null || (int) $user['status'] !== 1) {
            Session::forget(self::SESSION_KEY);

            return null;
        }

        return self::$cachedUser = $model->withoutHidden($user);
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string $role): bool
    {
        return self::role() === strtoupper($role);
    }

    /** ¿Tiene alguno de estos roles? */
    public static function hasRole(string ...$roles): bool
    {
        $current = self::role();

        if ($current === null) {
            return false;
        }

        foreach ($roles as $role) {
            if ($current === strtoupper($role)) {
                return true;
            }
        }

        return false;
    }

    /** ¿Su nivel jerárquico alcanza al rol pedido? SUPER_ADMIN > ADMIN > RECEPTION > BARBER */
    public static function atLeast(string $role): bool
    {
        return Role::level(self::role()) >= Role::level($role);
    }

    public static function isSuperAdmin(): bool
    {
        return self::is(Role::SUPER_ADMIN);
    }

    /** Id del barbero asociado al usuario (panel de barbero). */
    public static function barberId(): ?int
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        $row = Database::instance()->selectOne(
            'SELECT id FROM barbers WHERE user_id = :uid LIMIT 1',
            ['uid' => (int) $user['id']]
        );

        return $row ? (int) $row['id'] : null;
    }

    public static function displayName(): string
    {
        $user = self::user();

        return $user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : '';
    }

    // ---- Confirmación de contraseña para acciones sensibles (deploy, rollback) ----

    public static function confirmPassword(string $password): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        $row = (new User())->find((int) $user['id']);

        if ($row === null || !password_verify($password, (string) $row['password'])) {
            return false;
        }

        Session::put(self::CONFIRM_KEY, time());

        return true;
    }

    /** ¿La contraseña fue confirmada hace poco? (por defecto 15 minutos) */
    public static function recentlyConfirmed(int $seconds = 900): bool
    {
        $at = Session::get(self::CONFIRM_KEY);

        return $at !== null && (time() - (int) $at) <= $seconds;
    }

    // ---- Throttle de login ----

    private static function failureKey(string $email): string
    {
        return '_login_fail_' . sha1(strtolower($email));
    }

    public static function isLocked(string $email): bool
    {
        $state = Session::get(self::failureKey($email));

        return is_array($state)
            && ($state['count'] ?? 0) >= 5
            && (time() - (int) ($state['at'] ?? 0)) < 600;
    }

    public static function lockSecondsLeft(string $email): int
    {
        $state = Session::get(self::failureKey($email));

        return is_array($state) ? max(0, 600 - (time() - (int) ($state['at'] ?? 0))) : 0;
    }

    private static function recordFailure(string $email): void
    {
        $key   = self::failureKey($email);
        $state = Session::get($key, ['count' => 0, 'at' => time()]);

        if ((time() - (int) $state['at']) > 600) {
            $state = ['count' => 0, 'at' => time()];
        }

        $state['count']++;
        $state['at'] = time();

        Session::put($key, $state);
    }

    private static function clearFailures(string $email): void
    {
        Session::forget(self::failureKey($email));
    }
}
