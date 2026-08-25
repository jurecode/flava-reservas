<?php
/**
 * Ruta: /app/Support/PaymentMethod.php
 */

namespace App\Support;

final class PaymentMethod
{
    public const CASH        = 'cash';
    public const DEBIT       = 'debit';
    public const CREDIT      = 'credit';
    public const TRANSFER    = 'transfer';
    public const WEBPAY      = 'webpay';
    public const MERCADOPAGO = 'mercadopago';
    public const OTHER       = 'other';

    private const LABELS = [
        self::CASH        => 'Efectivo',
        self::DEBIT       => 'Débito',
        self::CREDIT      => 'Crédito',
        self::TRANSFER    => 'Transferencia',
        self::WEBPAY      => 'Webpay',
        self::MERCADOPAGO => 'Mercado Pago',
        self::OTHER       => 'Otro',
    ];

    /** Métodos que se pagan en el local (registro manual). */
    private const IN_STORE = [self::CASH, self::DEBIT, self::CREDIT, self::TRANSFER, self::OTHER];

    /** Pasarelas online (Etapa 2 — arquitectura ya preparada). */
    private const ONLINE = [self::WEBPAY, self::MERCADOPAGO];

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }

    public static function inStore(): array
    {
        return self::IN_STORE;
    }

    public static function online(): array
    {
        return self::ONLINE;
    }

    public static function isOnline(?string $method): bool
    {
        return in_array((string) $method, self::ONLINE, true);
    }

    public static function label(?string $method): string
    {
        return self::LABELS[(string) $method] ?? '—';
    }

    public static function isValid(?string $method): bool
    {
        return isset(self::LABELS[(string) $method]);
    }

    /** Métodos ofrecidos al cliente en el checkout público. */
    public static function forCheckout(): array
    {
        $enabled = (array) setting('payment_methods_public', [self::CASH, self::DEBIT, self::CREDIT, self::TRANSFER]);

        return array_values(array_filter($enabled, [self::class, 'isValid']));
    }

    /**
     * Nombre del ícono SVG del método. El mapa vive en App\Support\Icon para
     * que exista un solo lugar donde se decide la iconografía.
     */
    public static function icon(?string $method): string
    {
        return Icon::forPaymentMethod((string) $method);
    }
}
