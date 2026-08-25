<?php
/**
 * Ruta: /app/Services/Payments/WebpayGateway.php
 *
 * Adaptador de Transbank Webpay Plus. La integración real se implementa en la
 * Etapa 2; la arquitectura ya está lista para recibirla sin tocar el resto del
 * sistema. Las credenciales llegarán por variables de entorno / secretos.
 */

namespace App\Services\Payments;

final class WebpayGateway implements GatewayInterface
{
    public function name(): string
    {
        return 'Webpay';
    }

    public function isEnabled(): bool
    {
        return (bool) setting('webpay_enabled', false)
            && env('WEBPAY_COMMERCE_CODE') !== null
            && env('WEBPAY_API_KEY') !== null;
    }

    public function createTransaction(array $booking, float $amount, string $reference): array
    {
        throw new \RuntimeException('La integración con Webpay se habilitará en la Etapa 2.');
    }

    public function handleCallback(array $request): array
    {
        throw new \RuntimeException('La integración con Webpay se habilitará en la Etapa 2.');
    }
}
