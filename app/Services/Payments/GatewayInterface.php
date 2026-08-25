<?php
/**
 * Ruta: /app/Services/Payments/GatewayInterface.php
 * Contrato de las pasarelas de pago. Permite agregar Webpay/Mercado Pago sin
 * que ningún controlador dependa de un proveedor (spec §38).
 */

namespace App\Services\Payments;

interface GatewayInterface
{
    public function name(): string;

    public function isEnabled(): bool;

    /**
     * Inicia la transacción en el proveedor.
     *
     * @return array{redirect_url:string,transaction_id:?string,metadata:array}
     */
    public function createTransaction(array $booking, float $amount, string $reference): array;

    /**
     * Procesa el retorno del proveedor.
     *
     * @return array{approved:bool,transaction_id:?string,amount:?float,metadata:array}
     */
    public function handleCallback(array $request): array;
}
