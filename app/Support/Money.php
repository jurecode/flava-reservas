<?php
/**
 * Ruta: /app/Support/Money.php
 * Formato CLP. Se guarda DECIMAL(10,2) en base de datos; nunca FLOAT (spec §76).
 */

namespace App\Support;

final class Money
{
    /** 15000 -> "$15.000" */
    public static function format(int|float|string|null $amount, bool $withSymbol = true): string
    {
        $decimals  = (int) config('app.currency_decimals', 0);
        $formatted = number_format((float) ($amount ?? 0), $decimals, ',', '.');

        return $withSymbol ? config('app.currency_symbol', '$') . $formatted : $formatted;
    }

    /** "$15.000" o "15.000" -> 15000 */
    public static function parse(int|float|string|null $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }

        $digits = preg_replace('/[^0-9\-]/', '', (string) $value) ?? '';

        return $digits === '' ? 0 : (int) $digits;
    }

    /** Suma segura de montos monetarios (enteros CLP). */
    public static function sum(array $amounts): int
    {
        return array_reduce($amounts, static fn (int $carry, $item): int => $carry + self::parse($item), 0);
    }

    public static function percentage(int|float $amount, float $percent): int
    {
        return (int) round(((float) $amount) * ($percent / 100));
    }
}
