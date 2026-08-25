<?php
/**
 * Ruta: /app/Models/Customer.php
 * Ficha CRM generada automáticamente desde cada reserva (spec §12, §31).
 */

namespace App\Models;

use App\Support\Rut;
use App\Support\Str;
use Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';

    protected array $fillable = [
        'branch_id', 'first_name', 'last_name', 'rut', 'rut_normalized', 'email',
        'phone', 'whatsapp_phone', 'birthday', 'notes', 'preferred_barber_id',
        'accepts_marketing', 'status',
    ];

    /**
     * Busca un cliente existente por RUT, email o teléfono — en ese orden de
     * confianza — para evitar duplicados (spec §12).
     */
    public function findMatch(?string $rut, ?string $email, ?string $phone): ?array
    {
        $normalizedRut = $rut ? Rut::normalize($rut) : '';

        if ($normalizedRut !== '') {
            $found = $this->findBy('rut_normalized', $normalizedRut);
            if ($found !== null) {
                return $found;
            }
        }

        if ($email) {
            $found = $this->db()->selectOne(
                "SELECT * FROM {$this->table} WHERE email = :email ORDER BY id LIMIT 1",
                ['email' => mb_strtolower(trim($email))]
            );
            if ($found !== null) {
                return $found;
            }
        }

        $normalizedPhone = Str::phone($phone);
        if ($normalizedPhone !== null) {
            $found = $this->db()->selectOne(
                "SELECT * FROM {$this->table} WHERE phone = :phone ORDER BY id LIMIT 1",
                ['phone' => $normalizedPhone]
            );
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Buscador de recepción: nombre, RUT, teléfono o email (spec §90). */
    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $like    = '%' . $term . '%';
        $rutLike = '%' . Rut::clean($term) . '%';
        $digits  = preg_replace('/\D/', '', $term) ?? '';

        return $this->db()->select(
            "SELECT * FROM {$this->table}
             WHERE CONCAT(first_name, ' ', last_name) LIKE :name
                OR REPLACE(REPLACE(rut_normalized, '.', ''), '-', '') LIKE :rut
                OR email LIKE :email
                OR (:digits <> '' AND REPLACE(phone, '+', '') LIKE :phone)
             ORDER BY last_visit_at DESC, first_name
             LIMIT " . (int) $limit,
            [
                'name'   => $like,
                'rut'    => $rutLike,
                'email'  => $like,
                'digits' => $digits,
                'phone'  => '%' . $digits . '%',
            ]
        );
    }

    public function paginateFiltered(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['search'])) {
            $where[] = "(CONCAT(c.first_name, ' ', c.last_name) LIKE :search
                         OR c.rut_normalized LIKE :searchRut
                         OR c.email LIKE :searchEmail
                         OR c.phone LIKE :searchPhone)";

            $like                    = '%' . $filters['search'] . '%';
            $bindings['search']      = $like;
            $bindings['searchRut']   = '%' . Rut::clean($filters['search']) . '%';
            $bindings['searchEmail'] = $like;
            $bindings['searchPhone'] = $like;
        }

        if (!empty($filters['barber_id'])) {
            $where[]            = 'c.preferred_barber_id = :barber';
            $bindings['barber'] = (int) $filters['barber_id'];
        }

        if (($filters['only_no_show'] ?? '') === '1') {
            $where[] = 'c.no_show_count > 0';
        }

        $whereSql = implode(' AND ', $where);
        $orderBy  = match ($filters['sort'] ?? '') {
            'spent'  => 'c.total_spent DESC',
            'visits' => 'c.completed_bookings DESC',
            'name'   => 'c.first_name ASC, c.last_name ASC',
            default  => 'c.last_visit_at IS NULL, c.last_visit_at DESC, c.created_at DESC',
        };

        $total   = (int) $this->db()->scalar("SELECT COUNT(*) FROM {$this->table} c WHERE {$whereSql}", $bindings);
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);

        $rows = $this->db()->select(
            "SELECT c.*, b.display_name AS preferred_barber_name
             FROM {$this->table} c
             LEFT JOIN barbers b ON b.id = c.preferred_barber_id
             WHERE {$whereSql}
             ORDER BY {$orderBy}
             LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function fullName(array $customer): string
    {
        return trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    }

    /** Recalcula las métricas CRM del cliente desde sus reservas reales. */
    public function refreshStats(int $customerId): void
    {
        $stats = $this->db()->selectOne(
            "SELECT
                COUNT(*) AS total_bookings,
                SUM(status = 'completed') AS completed_bookings,
                SUM(status = 'cancelled') AS cancelled_bookings,
                SUM(status = 'no_show')   AS no_show_count,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS total_spent,
                MIN(CASE WHEN status = 'completed' THEN TIMESTAMP(booking_date, start_time) END) AS first_visit_at,
                MAX(CASE WHEN status = 'completed' THEN TIMESTAMP(booking_date, start_time) END) AS last_visit_at
             FROM bookings WHERE customer_id = :id",
            ['id' => $customerId]
        );

        if ($stats === null) {
            return;
        }

        $preferred = $this->db()->selectOne(
            "SELECT barber_id FROM bookings
             WHERE customer_id = :id AND status IN ('completed','confirmed','checked_in','in_progress')
             GROUP BY barber_id ORDER BY COUNT(*) DESC LIMIT 1",
            ['id' => $customerId]
        );

        $this->db()->update($this->table, [
            'total_bookings'      => (int) $stats['total_bookings'],
            'completed_bookings'  => (int) $stats['completed_bookings'],
            'cancelled_bookings'  => (int) $stats['cancelled_bookings'],
            'no_show_count'       => (int) $stats['no_show_count'],
            'total_spent'         => (float) $stats['total_spent'],
            'first_visit_at'      => $stats['first_visit_at'],
            'last_visit_at'       => $stats['last_visit_at'],
            'preferred_barber_id' => $preferred['barber_id'] ?? null,
            'updated_at'          => now()->format('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $customerId]);
    }

    /** Historial de reservas del cliente. */
    public function bookingHistory(int $customerId, int $limit = 50): array
    {
        return $this->db()->select(
            "SELECT bk.*, b.display_name AS barber_name, s.name AS service_current_name
             FROM bookings bk
             LEFT JOIN barbers b  ON b.id = bk.barber_id
             LEFT JOIN services s ON s.id = bk.service_id
             WHERE bk.customer_id = :id
             ORDER BY bk.booking_date DESC, bk.start_time DESC
             LIMIT " . (int) $limit,
            ['id' => $customerId]
        );
    }

    public function nextBooking(int $customerId): ?array
    {
        return $this->db()->selectOne(
            "SELECT bk.*, b.display_name AS barber_name
             FROM bookings bk
             LEFT JOIN barbers b ON b.id = bk.barber_id
             WHERE bk.customer_id = :id
               AND bk.status IN ('pending','confirmed','checked_in')
               AND TIMESTAMP(bk.booking_date, bk.start_time) >= NOW()
             ORDER BY bk.booking_date, bk.start_time
             LIMIT 1",
            ['id' => $customerId]
        );
    }
}
