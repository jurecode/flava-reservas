<?php
/**
 * Ruta: /app/Support/BookingStatus.php
 * Estados de reserva centralizados (spec §26).
 */

namespace App\Support;

final class BookingStatus
{
    public const PENDING     = 'pending';
    public const CONFIRMED   = 'confirmed';
    public const CHECKED_IN  = 'checked_in';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED   = 'completed';
    public const CANCELLED   = 'cancelled';
    public const NO_SHOW     = 'no_show';

    private const LABELS = [
        self::PENDING     => 'Pendiente',
        self::CONFIRMED   => 'Confirmado',
        self::CHECKED_IN  => 'Llegó',
        self::IN_PROGRESS => 'En atención',
        self::COMPLETED   => 'Finalizado',
        self::CANCELLED   => 'Cancelado',
        self::NO_SHOW     => 'No asistió',
    ];

    private const BADGES = [
        self::PENDING     => 'badge-pending',
        self::CONFIRMED   => 'badge-confirmed',
        self::CHECKED_IN  => 'badge-checkedin',
        self::IN_PROGRESS => 'badge-progress',
        self::COMPLETED   => 'badge-completed',
        self::CANCELLED   => 'badge-cancelled',
        self::NO_SHOW     => 'badge-noshow',
    ];

    /** Transiciones permitidas desde cada estado. */
    private const TRANSITIONS = [
        self::PENDING     => [self::CONFIRMED, self::CHECKED_IN, self::CANCELLED, self::NO_SHOW],
        self::CONFIRMED   => [self::CHECKED_IN, self::IN_PROGRESS, self::COMPLETED, self::CANCELLED, self::NO_SHOW],
        self::CHECKED_IN  => [self::IN_PROGRESS, self::COMPLETED, self::CANCELLED, self::NO_SHOW],
        self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
        self::COMPLETED   => [],
        self::CANCELLED   => [],
        self::NO_SHOW     => [self::COMPLETED],
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /** Estados que ocupan la agenda: bloquean el horario del barbero. */
    public static function blocking(): array
    {
        return [self::PENDING, self::CONFIRMED, self::CHECKED_IN, self::IN_PROGRESS, self::COMPLETED];
    }

    /** Estados que liberan el horario. */
    public static function released(): array
    {
        return [self::CANCELLED, self::NO_SHOW];
    }

    /** Estados considerados "activos" en la agenda del día. */
    public static function active(): array
    {
        return [self::PENDING, self::CONFIRMED, self::CHECKED_IN, self::IN_PROGRESS];
    }

    public static function label(?string $status): string
    {
        return self::LABELS[(string) $status] ?? '—';
    }

    public static function badgeClass(?string $status): string
    {
        return self::BADGES[(string) $status] ?? 'badge-muted';
    }

    public static function isValid(?string $status): bool
    {
        return isset(self::LABELS[(string) $status]);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function nextOptions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isCancellable(string $status): bool
    {
        return in_array($status, [self::PENDING, self::CONFIRMED, self::CHECKED_IN], true);
    }

    /** Color hexadecimal para el calendario. */
    public static function color(?string $status): string
    {
        return match ((string) $status) {
            self::PENDING     => '#E9A400',
            self::CONFIRMED   => '#FFC400',
            self::CHECKED_IN  => '#37B24D',
            self::IN_PROGRESS => '#1C7ED6',
            self::COMPLETED   => '#495057',
            self::CANCELLED   => '#ADB5BD',
            self::NO_SHOW     => '#E03131',
            default           => '#868E96',
        };
    }
}
