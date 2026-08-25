<?php
/**
 * Ruta: /app/Support/Rut.php
 * Validación y normalización del RUT chileno (spec §13).
 * Se guarda `rut` con formato de presentación y `rut_normalized` sin puntos ni
 * guion (índice único), permitiendo búsquedas rápidas y evitando duplicados.
 */

namespace App\Support;

final class Rut
{
    /** Deja sólo dígitos y el dígito verificador en mayúscula: "12345678K". */
    public static function clean(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9kK]/', '', (string) $rut) ?? '');
    }

    /** Formato canónico para almacenar/buscar: "12345678-K". */
    public static function normalize(?string $rut): string
    {
        $clean = self::clean($rut);

        if (strlen($clean) < 2) {
            return '';
        }

        return substr($clean, 0, -1) . '-' . substr($clean, -1);
    }

    /** Formato de presentación: "12.345.678-K". */
    public static function format(?string $rut): string
    {
        $clean = self::clean($rut);

        if (strlen($clean) < 2) {
            return (string) $rut;
        }

        $body = substr($clean, 0, -1);
        $dv   = substr($clean, -1);

        return number_format((int) $body, 0, '', '.') . '-' . $dv;
    }

    /** Calcula el dígito verificador (módulo 11). */
    public static function verifierDigit(string $body): string
    {
        $sum        = 0;
        $multiplier = 2;

        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum       += ((int) $body[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);

        return match ($remainder) {
            11      => '0',
            10      => 'K',
            default => (string) $remainder,
        };
    }

    public static function isValid(?string $rut): bool
    {
        $clean = self::clean($rut);

        if (strlen($clean) < 7 || strlen($clean) > 9) {
            return false;
        }

        $body = substr($clean, 0, -1);
        $dv   = substr($clean, -1);

        if (!ctype_digit($body) || (int) $body < 1_000_000) {
            return false;
        }

        return hash_equals(self::verifierDigit($body), $dv);
    }

    /** Número sin dígito verificador (útil para ordenar). */
    public static function number(?string $rut): ?int
    {
        $clean = self::clean($rut);

        return strlen($clean) > 1 ? (int) substr($clean, 0, -1) : null;
    }
}
