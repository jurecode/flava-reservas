<?php
/**
 * Ruta: /app/Support/PaymentStatus.php
 */

namespace App\Support;

final class PaymentStatus
{
    public const PENDING            = 'pending';
    public const PAID               = 'paid';
    public const FAILED             = 'failed';
    public const REFUNDED           = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';

    private const LABELS = [
        self::PENDING            => 'Pendiente',
        self::PAID               => 'Pagado',
        self::FAILED             => 'Fallido',
        self::REFUNDED           => 'Reembolsado',
        self::PARTIALLY_REFUNDED => 'Reembolso parcial',
    ];

    private const BADGES = [
        self::PENDING            => 'badge-pending',
        self::PAID               => 'badge-paid',
        self::FAILED             => 'badge-noshow',
        self::REFUNDED           => 'badge-muted',
        self::PARTIALLY_REFUNDED => 'badge-muted',
    ];

    public static function all(): array
    {
        return array_keys(self::LABELS);
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
}
