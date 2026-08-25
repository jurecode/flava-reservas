<?php
/**
 * Ruta: /app/Support/Str.php
 */

namespace App\Support;

final class Str
{
    public static function initials(?string $first, ?string $last = null): string
    {
        $a = mb_substr(trim((string) $first), 0, 1);
        $b = mb_substr(trim((string) $last), 0, 1);

        return mb_strtoupper($a . $b) ?: '?';
    }

    public static function limit(?string $text, int $length = 80, string $end = '…'): string
    {
        $text = trim((string) $text);

        return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length) . $end;
    }

    public static function titleCase(?string $text): string
    {
        return mb_convert_case(mb_strtolower(trim((string) $text)), MB_CASE_TITLE, 'UTF-8');
    }

    /** Normaliza teléfonos chilenos a formato E.164: +56912345678 */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $digits = ltrim($digits, '+');

        if (str_starts_with($digits, '56')) {
            return '+' . $digits;
        }
        if (strlen($digits) === 9) {
            return '+56' . $digits;
        }
        if (strlen($digits) === 8) {
            return '+569' . $digits;
        }

        return '+' . $digits;
    }

    /** Muestra un teléfono legible: +56 9 1234 5678 */
    public static function phoneDisplay(?string $phone): string
    {
        $normalized = self::phone($phone);

        if ($normalized === null) {
            return '';
        }

        if (preg_match('/^\+56(9)(\d{4})(\d{4})$/', $normalized, $m)) {
            return "+56 {$m[1]} {$m[2]} {$m[3]}";
        }

        return $normalized;
    }

    /** Enlace directo a WhatsApp. */
    public static function whatsappLink(?string $phone, string $message = ''): ?string
    {
        $normalized = self::phone($phone);

        if ($normalized === null) {
            return null;
        }

        return 'https://wa.me/' . ltrim($normalized, '+')
            . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }

    public static function uniqueSlug(string $text, string $table, string $column = 'slug', ?int $ignoreId = null): string
    {
        $base = slugify($text);
        $slug = $base;
        $i    = 2;

        $db = \Core\Database::instance();

        while (true) {
            $sql      = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :slug";
            $bindings = ['slug' => $slug];

            if ($ignoreId !== null) {
                $sql              .= ' AND id != :id';
                $bindings['id']    = $ignoreId;
            }

            if ((int) $db->scalar($sql, $bindings) === 0) {
                return $slug;
            }

            $slug = $base . '-' . $i++;
        }
    }
}
