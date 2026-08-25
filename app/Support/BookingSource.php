<?php
/**
 * Ruta: /app/Support/BookingSource.php
 * Origen de la reserva (spec §51).
 */

namespace App\Support;

final class BookingSource
{
    public const WEBSITE   = 'website';
    public const RECEPTION = 'reception';
    public const ADMIN     = 'admin';
    public const WHATSAPP  = 'whatsapp';
    public const PHONE     = 'phone';
    public const WALK_IN   = 'walk_in';

    private const LABELS = [
        self::WEBSITE   => 'Sitio web',
        self::RECEPTION => 'Recepción',
        self::ADMIN     => 'Administración',
        self::WHATSAPP  => 'WhatsApp',
        self::PHONE     => 'Teléfono',
        self::WALK_IN   => 'Sin reserva (walk-in)',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    /** Orígenes que puede elegir el personal al crear una reserva manual. */
    public static function manual(): array
    {
        return [self::RECEPTION, self::PHONE, self::WHATSAPP, self::WALK_IN, self::ADMIN];
    }

    public static function label(?string $source): string
    {
        return self::LABELS[(string) $source] ?? '—';
    }

    public static function isValid(?string $source): bool
    {
        return isset(self::LABELS[(string) $source]);
    }
}
