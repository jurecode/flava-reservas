<?php
/**
 * Ruta: /app/Services/CustomerService.php
 * CRM automático: cada reserva genera o actualiza una ficha de cliente sin
 * obligarlo a registrarse (spec §12).
 */

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Support\Rut;
use App\Support\Str;

final class CustomerService
{
    public function __construct(
        private readonly Customer $customers = new Customer(),
        private readonly CustomerNote $notes = new CustomerNote(),
    ) {
    }

    /**
     * Busca un cliente por RUT/email/teléfono y, si no existe, lo crea.
     * Si existe, completa los datos que estaban vacíos sin pisar los actuales.
     *
     * @return array{customer:array,created:bool}
     */
    public function findOrCreate(array $data, ?int $branchId = null): array
    {
        $normalized = $this->normalize($data);
        $existing   = $this->customers->findMatch(
            $normalized['rut_normalized'] ?? null,
            $normalized['email'] ?? null,
            $normalized['phone'] ?? null
        );

        if ($existing !== null) {
            $this->enrich($existing, $normalized);

            return ['customer' => $this->customers->find((int) $existing['id']) ?? $existing, 'created' => false];
        }

        $normalized['branch_id'] = $branchId;
        $normalized['status']    = 1;

        $id = $this->customers->create($normalized);

        ActivityLogger::log(
            'customer.created',
            'customer',
            $id,
            'Cliente creado automáticamente desde una reserva'
        );

        return ['customer' => $this->customers->find($id) ?? [], 'created' => true];
    }

    /** Alta manual desde recepción/administración. */
    public function create(array $data, ?int $branchId = null): array
    {
        $normalized = $this->normalize($data);

        if (!empty($normalized['rut_normalized'])) {
            $duplicate = $this->customers->findBy('rut_normalized', $normalized['rut_normalized']);

            if ($duplicate !== null) {
                throw new \RuntimeException('Ya existe un cliente con ese RUT: ' . $this->customers->fullName($duplicate));
            }
        }

        $normalized['branch_id'] = $branchId;
        $normalized['status']    = 1;

        $id = $this->customers->create($normalized);
        ActivityLogger::log('customer.created', 'customer', $id, 'Cliente creado manualmente');

        return $this->customers->find($id) ?? [];
    }

    public function update(int $id, array $data): array
    {
        $before     = $this->customers->findOrFail($id);
        $normalized = $this->normalize($data, false);

        if (!empty($normalized['rut_normalized'])) {
            $duplicate = $this->customers->findBy('rut_normalized', $normalized['rut_normalized']);

            if ($duplicate !== null && (int) $duplicate['id'] !== $id) {
                throw new \RuntimeException('Ya existe otro cliente con ese RUT.');
            }
        }

        $this->customers->update($id, $normalized);
        $after = $this->customers->find($id) ?? [];

        ActivityLogger::logChanges('customer.updated', 'customer', $id, $before, $after);

        return $after;
    }

    /**
     * Normaliza los datos de entrada: nombres capitalizados, RUT canónico,
     * email en minúsculas y teléfonos en formato E.164.
     */
    public function normalize(array $data, bool $onlyPresent = true): array
    {
        $result = [];

        if (!$onlyPresent || isset($data['first_name'])) {
            $result['first_name'] = Str::titleCase((string) ($data['first_name'] ?? ''));
        }
        if (!$onlyPresent || isset($data['last_name'])) {
            $result['last_name'] = Str::titleCase((string) ($data['last_name'] ?? ''));
        }

        if (array_key_exists('rut', $data)) {
            $rut = trim((string) $data['rut']);

            if ($rut !== '' && Rut::isValid($rut)) {
                $result['rut']            = Rut::format($rut);
                $result['rut_normalized'] = Rut::normalize($rut);
            } elseif ($rut === '') {
                $result['rut']            = null;
                $result['rut_normalized'] = null;
            }
        }

        if (array_key_exists('email', $data)) {
            $email            = mb_strtolower(trim((string) $data['email']));
            $result['email']  = $email !== '' ? $email : null;
        }

        if (array_key_exists('phone', $data)) {
            $result['phone'] = Str::phone((string) $data['phone']);
        }

        if (array_key_exists('whatsapp_phone', $data)) {
            $whatsapp                 = Str::phone((string) $data['whatsapp_phone']);
            $result['whatsapp_phone'] = $whatsapp ?: ($result['phone'] ?? null);
        } elseif (isset($result['phone']) && !$onlyPresent) {
            $result['whatsapp_phone'] = $result['phone'];
        }

        foreach (['birthday', 'notes', 'preferred_barber_id', 'accepts_marketing', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        return $result;
    }

    /** Completa datos faltantes de un cliente existente (nunca sobrescribe). */
    private function enrich(array $existing, array $incoming): void
    {
        $updates = [];

        foreach (['rut', 'rut_normalized', 'email', 'phone', 'whatsapp_phone'] as $field) {
            $current = $existing[$field] ?? null;
            $new     = $incoming[$field] ?? null;

            if (($current === null || $current === '') && $new !== null && $new !== '') {
                $updates[$field] = $new;
            }
        }

        // Si el nombre guardado quedó incompleto, mejorarlo.
        if (trim((string) ($existing['last_name'] ?? '')) === '' && !empty($incoming['last_name'])) {
            $updates['last_name'] = $incoming['last_name'];
        }

        if ($updates !== []) {
            $this->customers->update((int) $existing['id'], $updates);
        }
    }

    /** Ficha completa para el CRM (spec §31). */
    public function profile(int $customerId): array
    {
        $customer = $this->customers->findOrFail($customerId);

        return [
            'customer'      => $customer,
            'history'       => $this->customers->bookingHistory($customerId),
            'next_booking'  => $this->customers->nextBooking($customerId),
            'service_notes' => $this->notes->forCustomer($customerId, CustomerNote::TYPE_SERVICE),
            'admin_notes'   => $this->notes->forCustomer($customerId, CustomerNote::TYPE_ADMIN),
        ];
    }

    /** Vista reducida para el barbero: sin datos administrativos (spec §18). */
    public function profileForBarber(int $customerId): array
    {
        $customer = $this->customers->findOrFail($customerId);

        return [
            'customer' => [
                'id'                 => $customer['id'],
                'first_name'         => $customer['first_name'],
                'last_name'          => $customer['last_name'],
                'phone'              => $customer['phone'],
                'whatsapp_phone'     => $customer['whatsapp_phone'],
                'completed_bookings' => $customer['completed_bookings'],
                'no_show_count'      => $customer['no_show_count'],
                'first_visit_at'     => $customer['first_visit_at'],
                'last_visit_at'      => $customer['last_visit_at'],
            ],
            'history'       => array_map(
                static fn (array $booking): array => [
                    'booking_date' => $booking['booking_date'],
                    'start_time'   => $booking['start_time'],
                    'service_name' => $booking['service_name'],
                    'barber_name'  => $booking['barber_name'],
                    'status'       => $booking['status'],
                ],
                $this->customers->bookingHistory($customerId, 15)
            ),
            'service_notes' => $this->notes->forCustomer($customerId, CustomerNote::TYPE_SERVICE, 20),
        ];
    }

    public function addNote(int $customerId, string $note, string $type, ?int $authorId, ?int $bookingId = null, bool $pinned = false): int
    {
        $id = $this->notes->create([
            'customer_id' => $customerId,
            'booking_id'  => $bookingId,
            'author_id'   => $authorId,
            'type'        => in_array($type, [CustomerNote::TYPE_SERVICE, CustomerNote::TYPE_ADMIN], true) ? $type : CustomerNote::TYPE_SERVICE,
            'note'        => trim($note),
            'is_pinned'   => $pinned ? 1 : 0,
        ]);

        ActivityLogger::log('customer.note_added', 'customer', $customerId, 'Agregó una nota de cliente');

        return $id;
    }

    public function refreshStats(int $customerId): void
    {
        $this->customers->refreshStats($customerId);
    }
}
