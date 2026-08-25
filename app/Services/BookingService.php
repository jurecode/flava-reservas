<?php
/**
 * Ruta: /app/Services/BookingService.php
 *
 * Orquesta el ciclo de vida completo de una reserva. Los controladores sólo
 * reciben la solicitud y delegan aquí (spec §70).
 *
 * PREVENCIÓN DE CONCURRENCIA (spec §25):
 *   1. iniciar transacción
 *   2. re-verificar disponibilidad con SELECT ... FOR UPDATE
 *   3. insertar la reserva
 *   4. confirmar
 * Además, el índice único `uq_bookings_active_slot` de la tabla `bookings`
 * garantiza a nivel de motor que no existan dos reservas activas en el mismo
 * barbero/fecha/hora, incluso si dos procesos escaparan de la transacción.
 */

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\DateHelper;
use App\Support\PaymentMethod;
use App\Support\PaymentStatus;
use Core\Database;
use Core\Exceptions\BookingException;

final class BookingService
{
    public function __construct(
        private readonly Booking $bookings = new Booking(),
        private readonly BookingStatusHistory $history = new BookingStatusHistory(),
        private readonly Service $services = new Service(),
        private readonly Customer $customers = new Customer(),
        private readonly AvailabilityService $availability = new AvailabilityService(),
        private readonly CustomerService $customerService = new CustomerService(),
        private readonly NotificationService $notifications = new NotificationService(),
    ) {
    }

    /**
     * Crea una reserva completa (booking público o creación manual).
     *
     * @param array $payload {
     *   service_id, barber_id ('any' permitido), booking_date, start_time,
     *   customer: {first_name,last_name,rut,email,phone},
     *   payment_method, source, customer_notes, internal_notes,
     *   customer_id (opcional, para clientes ya existentes)
     * }
     * @param int|null $userId usuario interno que la crea (null = cliente web)
     *
     * @throws BookingException
     */
    public function create(array $payload, ?int $userId = null, bool $internal = false): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $date      = (string) ($payload['booking_date'] ?? '');
        $time      = substr((string) ($payload['start_time'] ?? ''), 0, 5);
        $branchId  = (int) ($payload['branch_id'] ?? Branch::defaultId());

        // "Cualquier barbero disponible": resolver antes de entrar a la transacción.
        $barberId = $payload['barber_id'] ?? null;

        if ($barberId === null || $barberId === '' || $barberId === 'any' || (int) $barberId === 0) {
            $barberId = $this->availability->firstAvailableBarber($serviceId, $date, $time, $internal);

            if ($barberId === null) {
                throw new BookingException('Ya no hay barberos disponibles a esa hora. Selecciona otro horario.');
            }
        }

        $barberId = (int) $barberId;
        $service  = $this->services->find($serviceId);

        if ($service === null) {
            throw new BookingException('El servicio seleccionado no existe.');
        }

        // Cliente: se busca o se crea ANTES de la transacción de agenda.
        if (!empty($payload['customer_id'])) {
            $customer = $this->customers->find((int) $payload['customer_id']);

            if ($customer === null) {
                throw new BookingException('El cliente seleccionado no existe.');
            }
        } else {
            $result   = $this->customerService->findOrCreate($payload['customer'] ?? [], $branchId);
            $customer = $result['customer'];
        }

        $db = Database::instance();

        try {
            $booking = $db->transaction(function () use (
                $barberId, $serviceId, $date, $time, $branchId, $customer, $service, $payload, $userId, $internal
            ): array {
                // ── Re-verificación bajo bloqueo: nunca confiar en el frontend ──
                $check = $this->availability->validateSlot(
                    $barberId,
                    $serviceId,
                    $date,
                    $time,
                    $internal,
                    null,
                    true // FOR UPDATE
                );

                if (!$check['ok']) {
                    throw new BookingException((string) $check['reason']);
                }

                $price    = (float) $check['price'];
                $discount = max(0.0, (float) ($payload['discount'] ?? 0));
                $total    = max(0.0, $price - $discount);

                $paymentMethod = $payload['payment_method'] ?? null;
                if ($paymentMethod !== null && !PaymentMethod::isValid((string) $paymentMethod)) {
                    $paymentMethod = null;
                }

                $source = (string) ($payload['source'] ?? BookingSource::WEBSITE);
                if (!BookingSource::isValid($source)) {
                    $source = BookingSource::WEBSITE;
                }

                $status = (bool) setting('auto_confirm', true)
                    ? BookingStatus::CONFIRMED
                    : BookingStatus::PENDING;

                // Walk-in: el cliente ya está en el local.
                if ($source === BookingSource::WALK_IN) {
                    $status = BookingStatus::CHECKED_IN;
                }

                $now  = now()->format('Y-m-d H:i:s');
                $code = $this->generateCode($date);

                $id = $this->bookings->create([
                    'public_code'      => $code,
                    'token'            => bin2hex(random_bytes(32)),
                    'branch_id'        => $branchId,
                    'customer_id'      => (int) $customer['id'],
                    'barber_id'        => $barberId,
                    'service_id'       => $serviceId,
                    'booking_date'     => $date,
                    'start_time'       => $time . ':00',
                    'end_time'         => $check['end_time'] . ':00',
                    'duration_minutes' => (int) $check['duration'],
                    'service_name'     => $service['name'],
                    'subtotal'         => $price,
                    'discount'         => $discount,
                    'total'            => $total,
                    'status'           => $status,
                    'payment_status'   => PaymentStatus::PENDING,
                    'payment_method'   => $paymentMethod,
                    'source'           => $source,
                    'customer_notes'   => $this->clean($payload['customer_notes'] ?? null),
                    'internal_notes'   => $this->clean($payload['internal_notes'] ?? null),
                    'created_by'       => $userId,
                    'confirmed_at'     => $status === BookingStatus::CONFIRMED ? $now : null,
                    'checked_in_at'    => $status === BookingStatus::CHECKED_IN ? $now : null,
                ]);

                $this->history->record($id, null, $status, $userId, 'Reserva creada');

                return $this->bookings->findFull($id) ?? [];
            });
        } catch (\PDOException $e) {
            // 23000 = violación del índice único uq_bookings_active_slot
            if ($e->getCode() === '23000') {
                logger()->warning('Colisión de reserva detectada por la base de datos', [
                    'barber_id' => $barberId,
                    'date'      => $date,
                    'time'      => $time,
                ]);

                throw BookingException::slotTaken();
            }

            throw $e;
        }

        $this->customerService->refreshStats((int) $customer['id']);
        $this->notifications->bookingCreated($booking);

        ActivityLogger::log(
            'booking.created',
            'booking',
            (int) $booking['id'],
            sprintf(
                'Reserva %s · %s · %s %s',
                $booking['public_code'],
                $booking['service_name'],
                $booking['booking_date'],
                substr((string) $booking['start_time'], 0, 5)
            ),
            null,
            ['status' => $booking['status'], 'total' => $booking['total']],
            $userId
        );

        return $booking;
    }

    /**
     * Reprograma una reserva reutilizando el mismo motor de disponibilidad.
     */
    public function reschedule(int $bookingId, string $date, string $time, ?int $barberId = null, ?int $userId = null, bool $internal = false): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new BookingException('La reserva no existe.');
        }

        if (!BookingStatus::isCancellable((string) $booking['status'])) {
            throw new BookingException('Esta reserva ya no puede reprogramarse.');
        }

        if (!$internal) {
            $this->assertWithinPolicy($booking, (int) setting('reschedule_limit_hours', 2), 'reprogramar');
        }

        $barberId ??= (int) $booking['barber_id'];
        $time       = substr($time, 0, 5);

        $updated = Database::instance()->transaction(function () use ($booking, $barberId, $date, $time, $userId, $internal, $bookingId): array {
            $check = $this->availability->validateSlot(
                $barberId,
                (int) $booking['service_id'],
                $date,
                $time,
                $internal,
                $bookingId,
                true
            );

            if (!$check['ok']) {
                throw new BookingException((string) $check['reason']);
            }

            $this->bookings->update($bookingId, [
                'barber_id'        => $barberId,
                'booking_date'     => $date,
                'start_time'       => $time . ':00',
                'end_time'         => $check['end_time'] . ':00',
                'duration_minutes' => (int) $check['duration'],
            ]);

            $this->history->record(
                $bookingId,
                (string) $booking['status'],
                (string) $booking['status'],
                $userId,
                sprintf(
                    'Reprogramada: %s %s → %s %s',
                    $booking['booking_date'],
                    substr((string) $booking['start_time'], 0, 5),
                    $date,
                    $time
                )
            );

            return $this->bookings->findFull($bookingId) ?? [];
        });

        // Los recordatorios de la fecha anterior ya no aplican.
        $this->notifications->cancelReminders($bookingId);
        $this->notifications->bookingRescheduled($updated);

        ActivityLogger::log(
            'booking.rescheduled',
            'booking',
            $bookingId,
            'Reserva ' . $booking['public_code'] . ' reprogramada',
            ['booking_date' => $booking['booking_date'], 'start_time' => $booking['start_time'], 'barber_id' => $booking['barber_id']],
            ['booking_date' => $date, 'start_time' => $time, 'barber_id' => $barberId],
            $userId
        );

        return $updated;
    }

    /** Cancela una reserva aplicando la política configurable (spec §30). */
    public function cancel(int $bookingId, ?string $reason = null, ?int $userId = null, bool $internal = false): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new BookingException('La reserva no existe.');
        }

        if (in_array($booking['status'], [BookingStatus::CANCELLED, BookingStatus::COMPLETED], true)) {
            throw new BookingException('Esta reserva ya no puede cancelarse.');
        }

        if (!$internal) {
            $this->assertWithinPolicy($booking, (int) setting('cancel_limit_hours', 2), 'cancelar');
        }

        $this->bookings->update($bookingId, [
            'status'              => BookingStatus::CANCELLED,
            'cancelled_at'        => now()->format('Y-m-d H:i:s'),
            'cancelled_by'        => $userId,
            'cancellation_reason' => $this->clean($reason),
        ]);

        $this->history->record($bookingId, (string) $booking['status'], BookingStatus::CANCELLED, $userId, $reason);
        $this->customerService->refreshStats((int) $booking['customer_id']);

        $this->notifications->cancelReminders($bookingId);
        $updated = $this->bookings->findFull($bookingId) ?? [];
        $this->notifications->bookingCancelled($updated);

        ActivityLogger::log(
            'booking.cancelled',
            'booking',
            $bookingId,
            'Reserva ' . $booking['public_code'] . ' cancelada' . ($reason ? ': ' . $reason : ''),
            ['status' => $booking['status']],
            ['status' => BookingStatus::CANCELLED],
            $userId
        );

        return $updated;
    }

    /** Cambia el estado validando las transiciones permitidas (spec §26). */
    public function changeStatus(int $bookingId, string $newStatus, ?int $userId = null, ?string $note = null): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new BookingException('La reserva no existe.');
        }

        if (!BookingStatus::isValid($newStatus)) {
            throw new BookingException('Estado de reserva inválido.');
        }

        $current = (string) $booking['status'];

        if ($current === $newStatus) {
            return $booking;
        }

        if (!BookingStatus::canTransition($current, $newStatus)) {
            throw new BookingException(sprintf(
                'No se puede pasar de "%s" a "%s".',
                BookingStatus::label($current),
                BookingStatus::label($newStatus)
            ));
        }

        $now     = now()->format('Y-m-d H:i:s');
        $updates = ['status' => $newStatus];

        match ($newStatus) {
            BookingStatus::CONFIRMED   => $updates['confirmed_at']  = $now,
            BookingStatus::CHECKED_IN  => $updates['checked_in_at'] = $now,
            BookingStatus::IN_PROGRESS => $updates['started_at']    = $now,
            BookingStatus::COMPLETED   => $updates['completed_at']  = $now,
            BookingStatus::CANCELLED   => $updates['cancelled_at']  = $now,
            default                    => null,
        };

        $this->bookings->update($bookingId, $updates);
        $this->history->record($bookingId, $current, $newStatus, $userId, $note);
        $this->customerService->refreshStats((int) $booking['customer_id']);

        if (in_array($newStatus, [BookingStatus::CANCELLED, BookingStatus::NO_SHOW, BookingStatus::COMPLETED], true)) {
            $this->notifications->cancelReminders($bookingId);
        }

        ActivityLogger::log(
            $newStatus === BookingStatus::NO_SHOW ? 'booking.no_show' : 'booking.status',
            'booking',
            $bookingId,
            sprintf('%s: %s → %s', $booking['public_code'], BookingStatus::label($current), BookingStatus::label($newStatus)),
            ['status' => $current],
            ['status' => $newStatus],
            $userId
        );

        return $this->bookings->findFull($bookingId) ?? [];
    }

    /** Edición desde administración: servicio, notas, descuento. */
    public function update(int $bookingId, array $data, ?int $userId = null): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new BookingException('La reserva no existe.');
        }

        $updates = [];

        // Cambiar de servicio recalcula duración, precio y hora de término.
        if (!empty($data['service_id']) && (int) $data['service_id'] !== (int) $booking['service_id']) {
            $service = $this->services->find((int) $data['service_id']);

            if ($service === null) {
                throw new BookingException('El servicio seleccionado no existe.');
            }

            $effective = $this->services->effectiveFor($service, (int) $booking['barber_id']);
            $start     = substr((string) $booking['start_time'], 0, 5);
            $end       = DateHelper::addMinutes($start, (int) $effective['duration']);

            $check = $this->availability->validateSlot(
                (int) $booking['barber_id'],
                (int) $service['id'],
                (string) $booking['booking_date'],
                $start,
                true,
                $bookingId
            );

            if (!$check['ok']) {
                throw new BookingException('El nuevo servicio no cabe en ese horario: ' . $check['reason']);
            }

            $updates['service_id']       = (int) $service['id'];
            $updates['service_name']     = $service['name'];
            $updates['duration_minutes'] = (int) $effective['duration'];
            $updates['end_time']         = $end . ':00';
            $updates['subtotal']         = (float) $effective['price'];
        }

        if (array_key_exists('discount', $data)) {
            $updates['discount'] = max(0.0, (float) $data['discount']);
        }

        if (isset($updates['subtotal']) || isset($updates['discount'])) {
            $subtotal          = $updates['subtotal'] ?? (float) $booking['subtotal'];
            $discount          = $updates['discount'] ?? (float) $booking['discount'];
            $updates['total']  = max(0.0, $subtotal - $discount);
        }

        foreach (['customer_notes', 'internal_notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $this->clean($data[$field]);
            }
        }

        if (!empty($data['payment_method']) && PaymentMethod::isValid((string) $data['payment_method'])) {
            $updates['payment_method'] = $data['payment_method'];
        }

        if ($updates === []) {
            return $booking;
        }

        $this->bookings->update($bookingId, $updates);
        $updated = $this->bookings->findFull($bookingId) ?? [];

        ActivityLogger::logChanges('booking.updated', 'booking', $bookingId, $booking, $updated, 'Reserva ' . $booking['public_code']);

        return $updated;
    }

    /** Cambia el barbero manteniendo fecha y hora (acción típica de recepción). */
    public function changeBarber(int $bookingId, int $barberId, ?int $userId = null): array
    {
        $booking = $this->bookings->findFull($bookingId);

        if ($booking === null) {
            throw new BookingException('La reserva no existe.');
        }

        return $this->reschedule(
            $bookingId,
            (string) $booking['booking_date'],
            substr((string) $booking['start_time'], 0, 5),
            $barberId,
            $userId,
            true
        );
    }

    /**
     * Código público legible: FLV-260824-A7C2 (spec §27).
     * No expone IDs incrementales.
     */
    public function generateCode(string $date): string
    {
        $prefix = (string) config('app.booking.code_prefix', 'FLV');
        $day    = DateHelper::make($date)->format('ymd');

        for ($attempt = 0; $attempt < 12; $attempt++) {
            // Alfabeto sin caracteres ambiguos (0/O, 1/I).
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $suffix   = '';

            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $code = sprintf('%s-%s-%s', $prefix, $day, $suffix);

            if (!$this->bookings->codeExists($code)) {
                return $code;
            }
        }

        // Fallback prácticamente inalcanzable.
        return sprintf('%s-%s-%s', $prefix, $day, strtoupper(bin2hex(random_bytes(3))));
    }

    /** Enlace privado de gestión sin cuenta (spec §29). */
    public function manageUrl(array $booking): string
    {
        $token = (string) ($booking['token'] ?? $this->bookings->find((int) $booking['id'])['token'] ?? '');

        return url('reserva/' . $booking['public_code']) . '?token=' . $token;
    }

    /** Política de cancelación/reprogramación configurable. */
    private function assertWithinPolicy(array $booking, int $limitHours, string $action): void
    {
        if ($limitHours <= 0) {
            return;
        }

        $start = DateHelper::make($booking['booking_date'] . ' ' . $booking['start_time']);
        $limit = DateHelper::make()->modify("+{$limitHours} hours");

        if ($start < $limit) {
            throw new BookingException(sprintf(
                'No es posible %s con menos de %d %s de anticipación. Comunícate con nosotros.',
                $action,
                $limitHours,
                $limitHours === 1 ? 'hora' : 'horas'
            ));
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 2000);
    }
}
