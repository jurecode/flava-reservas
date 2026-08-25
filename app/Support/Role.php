<?php
/**
 * Ruta: /app/Support/Role.php
 * Roles internos del sistema. Nunca escribir estos strings sueltos en el código.
 */

namespace App\Support;

final class Role
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const ADMIN       = 'ADMIN';
    public const RECEPTION   = 'RECEPTION';
    public const BARBER      = 'BARBER';

    /** Jerarquía: mayor número = más privilegios. */
    private const LEVELS = [
        self::BARBER      => 10,
        self::RECEPTION   => 20,
        self::ADMIN       => 30,
        self::SUPER_ADMIN => 40,
    ];

    private const LABELS = [
        self::SUPER_ADMIN => 'Súper Administrador',
        self::ADMIN       => 'Administrador',
        self::RECEPTION   => 'Recepción',
        self::BARBER      => 'Barbero',
    ];

    /** @return array<int,string> */
    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /** Roles que un usuario puede asignar (nunca por encima del suyo). */
    public static function assignableBy(?string $role): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $candidate): bool => self::level($candidate) <= self::level($role)
        ));
    }

    public static function label(?string $role): string
    {
        return self::LABELS[strtoupper((string) $role)] ?? '—';
    }

    public static function level(?string $role): int
    {
        return self::LEVELS[strtoupper((string) $role)] ?? 0;
    }

    public static function isValid(?string $role): bool
    {
        return isset(self::LEVELS[strtoupper((string) $role)]);
    }

    /** Panel inicial según el rol (destino tras el login). */
    public static function homeFor(?string $role): string
    {
        return match (strtoupper((string) $role)) {
            self::SUPER_ADMIN, self::ADMIN => '/admin',
            self::RECEPTION                => '/recepcion',
            self::BARBER                   => '/barbero',
            default                        => '/',
        };
    }

    public static function badgeClass(?string $role): string
    {
        return match (strtoupper((string) $role)) {
            self::SUPER_ADMIN => 'badge-super',
            self::ADMIN       => 'badge-admin',
            self::RECEPTION   => 'badge-reception',
            self::BARBER      => 'badge-barber',
            default           => 'badge-muted',
        };
    }
}
