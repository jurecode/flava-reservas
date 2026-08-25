<?php
/**
 * Ruta: /app/Support/DateHelper.php
 * Fechas en español y utilidades de agenda. Zona horaria única: America/Santiago.
 */

namespace App\Support;

final class DateHelper
{
    public const DAYS = [
        1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves',
        5 => 'viernes', 6 => 'sábado', 7 => 'domingo',
    ];

    public const DAYS_SHORT = [
        1 => 'LUN', 2 => 'MAR', 3 => 'MIÉ', 4 => 'JUE',
        5 => 'VIE', 6 => 'SÁB', 7 => 'DOM',
    ];

    public const MONTHS = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public const MONTHS_SHORT = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    public static function tz(): \DateTimeZone
    {
        return new \DateTimeZone(config('app.timezone', 'America/Santiago'));
    }

    public static function make(string $datetime = 'now'): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, self::tz());
    }

    /** "viernes 28 de agosto" (o con año). */
    public static function longEs(string $date, bool $withYear = false, bool $withWeekday = true): string
    {
        $dt = self::make($date);

        $text = ($withWeekday ? self::DAYS[(int) $dt->format('N')] . ' ' : '')
            . (int) $dt->format('j') . ' de ' . self::MONTHS[(int) $dt->format('n')];

        return $withYear ? $text . ' de ' . $dt->format('Y') : $text;
    }

    /** "VIE 28" para el selector de fechas del booking. */
    public static function chipEs(string $date): string
    {
        $dt = self::make($date);

        return self::DAYS_SHORT[(int) $dt->format('N')] . ' ' . (int) $dt->format('j');
    }

    /** "28 ago" */
    public static function shortEs(string $date): string
    {
        $dt = self::make($date);

        return (int) $dt->format('j') . ' ' . strtolower(self::MONTHS_SHORT[(int) $dt->format('n')]);
    }

    public static function isToday(string $date): bool
    {
        return self::make($date)->format('Y-m-d') === self::make()->format('Y-m-d');
    }

    public static function isTomorrow(string $date): bool
    {
        return self::make($date)->format('Y-m-d') === self::make('+1 day')->format('Y-m-d');
    }

    public static function isPast(string $datetime): bool
    {
        return self::make($datetime) < self::make();
    }

    /** Etiqueta amigable: HOY / MAÑANA / VIE 28 */
    public static function friendly(string $date): string
    {
        if (self::isToday($date)) {
            return 'HOY';
        }
        if (self::isTomorrow($date)) {
            return 'MAÑANA';
        }

        return self::chipEs($date);
    }

    /** Día de la semana ISO: 1 = lunes ... 7 = domingo. */
    public static function weekday(string $date): int
    {
        return (int) self::make($date)->format('N');
    }

    /** "09:00" + 45 min => "09:45" */
    public static function addMinutes(string $time, int $minutes): string
    {
        return self::make('2000-01-01 ' . $time)->modify("+{$minutes} minutes")->format('H:i');
    }

    public static function minutesBetween(string $start, string $end): int
    {
        return (int) round((strtotime('2000-01-01 ' . $end) - strtotime('2000-01-01 ' . $start)) / 60);
    }

    /** Minutos desde medianoche — base del motor de disponibilidad. */
    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $hours * 60 + $minutes;
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    /** ¿Se solapan dos rangos [aStart,aEnd) y [bStart,bEnd)? */
    public static function overlaps(int $aStart, int $aEnd, int $bStart, int $bEnd): bool
    {
        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /** Rango de fechas inclusivo: ['2026-08-24', '2026-08-25', ...] */
    public static function range(string $from, string $to): array
    {
        $dates   = [];
        $current = self::make($from);
        $limit   = self::make($to);

        while ($current <= $limit) {
            $dates[]  = $current->format('Y-m-d');
            $current  = $current->modify('+1 day');
        }

        return $dates;
    }

    /** Lunes de la semana de $date. */
    public static function startOfWeek(string $date): string
    {
        $dt = self::make($date);

        return $dt->modify('-' . ((int) $dt->format('N') - 1) . ' days')->format('Y-m-d');
    }

    public static function endOfWeek(string $date): string
    {
        return self::make(self::startOfWeek($date))->modify('+6 days')->format('Y-m-d');
    }

    public static function startOfMonth(string $date): string
    {
        return self::make($date)->modify('first day of this month')->format('Y-m-d');
    }

    public static function endOfMonth(string $date): string
    {
        return self::make($date)->modify('last day of this month')->format('Y-m-d');
    }

    /** "hace 3 días" / "en 2 horas" */
    public static function humanDiff(string $datetime): string
    {
        $then = self::make($datetime);
        $now  = self::make();
        $diff = $now->diff($then);
        $past = $then < $now;

        $amount = match (true) {
            $diff->y > 0 => $diff->y . ' año' . ($diff->y > 1 ? 's' : ''),
            $diff->m > 0 => $diff->m . ' mes' . ($diff->m > 1 ? 'es' : ''),
            $diff->d > 0 => $diff->d . ' día' . ($diff->d > 1 ? 's' : ''),
            $diff->h > 0 => $diff->h . ' hora' . ($diff->h > 1 ? 's' : ''),
            $diff->i > 0 => $diff->i . ' minuto' . ($diff->i > 1 ? 's' : ''),
            default      => 'unos segundos',
        };

        return $past ? "hace {$amount}" : "en {$amount}";
    }
}
