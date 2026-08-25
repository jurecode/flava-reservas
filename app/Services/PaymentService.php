<?php
/**
 * Ruta: /app/Services/PaymentService.php
 *
 * Registro de pagos y punto único de integración con pasarelas. BookingController
 * nunca depende de un proveedor concreto (spec §38): Webpay o Mercado Pago se
 * agregan implementando GatewayInterface y registrando el adaptador aquí.
 */

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\GatewayInterface;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\WebpayGateway;
use App\Support\PaymentMethod;
use App\Support\PaymentStatus;
use Core\Database;

final class PaymentService
{
    public function __construct(
        private readonly Payment $payments = new Payment(),
        private readonly Booking $bookings = new Booking(),
        private readonly CustomerService $customerService = new CustomerService(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /**
     * Registra un pago manual (efectivo, débito, crédito, transferencia).
     * Recalcula el estado de pago de la reserva según el total abonado.
     */
    public function registerManual(int $bookingId, float $amount, string $method, ?int $userId = null, ?string $notes = null): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new \RuntimeException('La reserva no existe.');
        }

        if (!PaymentMethod::isValid($method)) {
            throw new \RuntimeException('Método de pago inválido.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('El monto debe ser mayor a cero.');
        }

        $payment = Database::instance()->transaction(function () use ($booking, $bookingId, $amount, $method, $userId, $notes): array {
            $paymentId = $this->payments->create([
                'booking_id'     => $bookingId,
                'customer_id'    => (int) $booking['customer_id'],
                'amount'         => $amount,
                'payment_method' => $method,
                'status'         => PaymentStatus::PAID,
                'provider'       => 'manual',
                'notes'          => $notes,
                'registered_by'  => $userId,
                'paid_at'        => now()->format('Y-m-d H:i:s'),
            ]);

            $this->syncBookingPaymentStatus($bookingId, $method);

            return $this->payments->find($paymentId) ?? [];
        });

        $this->customerService->refreshStats((int) $booking['customer_id']);

        $updated = $this->bookings->findFull($bookingId) ?? $booking;
        if ($updated['payment_status'] === PaymentStatus::PAID) {
            $this->notifications->paymentReceived($updated, $amount);
        }

        ActivityLogger::log(
            'payment.registered',
            'booking',
            $bookingId,
            sprintf('Pago %s por %s (%s)', $booking['public_code'], money($amount), PaymentMethod::label($method)),
            null,
            ['amount' => $amount, 'method' => $method],
            $userId
        );

        return $payment;
    }

    /** Reembolso (total o parcial). */
    public function refund(int $paymentId, ?float $amount = null, ?int $userId = null, ?string $reason = null): array
    {
        $payment = $this->payments->findOrFail($paymentId);

        if ($payment['status'] !== PaymentStatus::PAID) {
            throw new \RuntimeException('Sólo se pueden reembolsar pagos confirmados.');
        }

        $amount  ??= (float) $payment['amount'];
        $isPartial = $amount < (float) $payment['amount'];

        $this->payments->update($paymentId, [
            'status'      => $isPartial ? PaymentStatus::PARTIALLY_REFUNDED : PaymentStatus::REFUNDED,
            'refunded_at' => now()->format('Y-m-d H:i:s'),
            'notes'       => trim(((string) $payment['notes']) . ' | Reembolso: ' . (string) $reason),
        ]);

        if ($payment['booking_id'] !== null) {
            $this->syncBookingPaymentStatus((int) $payment['booking_id']);
            $this->customerService->refreshStats((int) $payment['customer_id']);
        }

        ActivityLogger::log(
            'payment.refunded',
            'payment',
            $paymentId,
            sprintf('Reembolso de %s', money($amount)),
            ['status' => $payment['status']],
            ['status' => $isPartial ? PaymentStatus::PARTIALLY_REFUNDED : PaymentStatus::REFUNDED],
            $userId
        );

        return $this->payments->find($paymentId) ?? [];
    }

    /** Recalcula el estado de pago de la reserva desde los pagos reales. */
    public function syncBookingPaymentStatus(int $bookingId, ?string $method = null): void
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null) {
            return;
        }

        $paid  = $this->payments->paidTotal($bookingId);
        $total = (float) $booking['total'];

        $status = match (true) {
            $paid <= 0                  => PaymentStatus::PENDING,
            $paid + 0.01 >= $total      => PaymentStatus::PAID,
            default                     => PaymentStatus::PENDING, // abono parcial: sigue pendiente
        };

        $updates = ['payment_status' => $status];

        if ($method !== null) {
            $updates['payment_method'] = $method;
        }

        $this->bookings->update($bookingId, $updates);
    }

    /** Saldo pendiente de una reserva. */
    public function balanceFor(int $bookingId): float
    {
        $booking = $this->bookings->find($bookingId);

        if ($booking === null) {
            return 0.0;
        }

        return max(0.0, (float) $booking['total'] - $this->payments->paidTotal($bookingId));
    }

    // -----------------------------------------------------------------
    //  Pasarelas online (Etapa 2)
    // -----------------------------------------------------------------

    /** Resuelve el adaptador del proveedor. */
    public function gateway(string $provider): GatewayInterface
    {
        return match ($provider) {
            PaymentMethod::WEBPAY      => new WebpayGateway(),
            PaymentMethod::MERCADOPAGO => new MercadoPagoGateway(),
            default                    => throw new \RuntimeException('Pasarela no soportada: ' . $provider),
        };
    }

    /** @return array<int,string> proveedores online activos */
    public function activeGateways(): array
    {
        return array_values(array_filter(
            PaymentMethod::online(),
            fn (string $provider): bool => $this->gateway($provider)->isEnabled()
        ));
    }

    /**
     * Inicia un pago online. Devuelve la URL a la que redirigir al cliente.
     * El registro `payments` queda en estado `pending` hasta el callback.
     */
    public function startOnlinePayment(int $bookingId, string $provider): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new \RuntimeException('La reserva no existe.');
        }

        $gateway = $this->gateway($provider);

        if (!$gateway->isEnabled()) {
            throw new \RuntimeException('El pago online no está disponible por ahora.');
        }

        $paymentId = $this->payments->create([
            'booking_id'     => $bookingId,
            'customer_id'    => (int) $booking['customer_id'],
            'amount'         => (float) $booking['total'],
            'payment_method' => $provider,
            'status'         => PaymentStatus::PENDING,
            'provider'       => $provider,
        ]);

        $result = $gateway->createTransaction($booking, (float) $booking['total'], (string) $paymentId);

        $this->payments->update($paymentId, [
            'transaction_id' => $result['transaction_id'] ?? null,
            'metadata'       => json_encode($result['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);

        return $result + ['payment_id' => $paymentId];
    }
}
