<?php
/**
 * Ruta: /app/Services/Payments/MercadoPagoGateway.php
 *
 * Adaptador de Mercado Pago (Checkout Pro). Pendiente para la Etapa 2.
 */

namespace App\Services\Payments;

final class MercadoPagoGateway implements GatewayInterface
{
    public function name(): string
    {
        return 'Mercado Pago';
    }

    public function isEnabled(): bool
    {
        return (bool) setting('mercadopago_enabled', false)
            && env('MERCADOPAGO_ACCESS_TOKEN') !== null;
    }

    public function createTransaction(array $booking, float $amount, string $reference): array
    {
        throw new \RuntimeException('La integración con Mercado Pago se habilitará en la Etapa 2.');
    }

    public function handleCallback(array $request): array
    {
        throw new \RuntimeException('La integración con Mercado Pago se habilitará en la Etapa 2.');
    }
}
