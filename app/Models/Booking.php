<?php
/**
 * Ruta: /app/Models/Booking.php
 * Persistencia de reservas. La lógica de negocio vive en BookingService y la
 * disponibilidad en AvailabilityService (spec §70).
 */

namespace App\Models;

use App\Support\BookingStatus;
use Core\Model;

class Booking extends Model
{
    protected string $table = 'bookings';

    protected array $fillable = [
        'public_code', 'token', 'branch_id', 'customer_id', 'barber_id', 'service_id',
        'booking_date', 'start_time', 'end_time', 'duration_minutes', 'service_name',
        'subtotal', 'discount', 'total', 'status', 'payment_status', 'payment_method',
        'source', 'coupon_id', 'customer_notes', 'internal_notes', 'cancellation_reason',
        'reminder_24h_sent', 'reminder_2h_sent', 'confirmed_at', 'checked_in_at',
        'started_at', 'completed_at', 'cancelled_at', 'created_by', 'cancelled_by',
    ];

    /** El token nunca debe viajar en listados ni respuestas JSON. */
    protected array $hidden = ['token'];

    private const SELECT_FULL = "
        SELECT bk.*,
               c.first_name AS customer_first_name, c.last_name AS customer_last_name,
               c.rut AS customer_rut, c.phone AS customer_phone, c.email AS customer_email,
               c.whatsapp_phone AS customer_whatsapp,
               c.no_show_count AS customer_no_shows, c.completed_bookings AS customer_visits,
               b.display_name AS barber_name, b.color AS barber_color, b.photo AS barber_photo,
               s.name AS service_current_name, s.slug AS service_slug,
               br.name AS branch_name,
               u.first_name AS created_by_name
        FROM bookings bk
        INNER JOIN customers c ON c.id = bk.customer_id
        INNER JOIN barbers   b ON b.id = bk.barber_id
        INNER JOIN services  s ON s.id = bk.service_id
        LEFT  JOIN branches br ON br.id = bk.branch_id
        LEFT  JOIN users     u ON u.id = bk.created_by";

    public function findFull(int $id): ?array
    {
        return $this->db()->selectOne(self::SELECT_FULL . ' WHERE bk.id = :id LIMIT 1', ['id' => $id]);
    }

    public function findByCode(string $code): ?array
    {
        return $this->db()->selectOne(
            self::SELECT_FULL . ' WHERE bk.public_code = :code LIMIT 1',
            ['code' => strtoupper(trim($code))]
        );
    }

    /**
     * Búsqueda para la gestión sin cuenta: exige código + token válido.
     * La comparación del token es en tiempo constante (spec §29, §95).
     */
    public function findByCodeAndToken(string $code, string $token): ?array
    {
        $booking = $this->findByCode($code);

        if ($booking === null) {
            return null;
        }

        $stored = (string) ($this->find((int) $booking['id'])['token'] ?? '');

        return hash_equals($stored, trim($token)) ? $booking : null;
    }

    public function codeExists(string $code): bool
    {
        return (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM {$this->table} WHERE public_code = :code",
            ['code' => $code]
        ) > 0;
    }

    /** Agenda de un barbero para un día. */
    public function agendaFor(int $barberId, string $date): array
    {
        return $this->db()->select(
            self::SELECT_FULL . " WHERE bk.barber_id = :barber AND bk.booking_date = :date
                                  ORDER BY bk.start_time",
            ['barber' => $barberId, 'date' => $date]
        );
    }

    /** Agenda completa del día (todos los barberos). */
    public function agendaForDate(string $date, ?int $branchId = null): array
    {
        $sql      = self::SELECT_FULL . ' WHERE bk.booking_date = :date';
        $bindings = ['date' => $date];

        if ($branchId !== null) {
            $sql               .= ' AND bk.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select($sql . ' ORDER BY bk.start_time, b.sort_order', $bindings);
    }

    /** Reservas de un rango (calendario administrativo / FullCalendar). */
    public function forRange(string $from, string $to, array $filters = []): array
    {
        $sql      = self::SELECT_FULL . ' WHERE bk.booking_date BETWEEN :from AND :to';
        $bindings = ['from' => $from, 'to' => $to];

        if (!empty($filters['barber_id'])) {
            $sql               .= ' AND bk.barber_id = :barber';
            $bindings['barber'] = (int) $filters['barber_id'];
        }
        if (!empty($filters['status'])) {
            $sql               .= ' AND bk.status = :status';
            $bindings['status'] = $filters['status'];
        } else {
            $sql .= " AND bk.status <> 'cancelled'";
        }
        if (!empty($filters['branch_id'])) {
            $sql               .= ' AND bk.branch_id = :branch2';
            $bindings['branch2'] = (int) $filters['branch_id'];
        }

        return $this->db()->select($sql . ' ORDER BY bk.booking_date, bk.start_time', $bindings);
    }

    /**
     * Reservas que ocupan la agenda de un barbero en una fecha.
     * Sólo estados que bloquean el horario (spec §24).
     *
     * @param bool $forUpdate true dentro de la transacción de creación: bloquea
     *                        el rango para impedir reservas simultáneas (spec §25).
     */
    public function occupiedSlots(int $barberId, string $date, bool $forUpdate = false, ?int $excludeId = null): array
    {
        $statuses     = BookingStatus::blocking();
        $placeholders = [];
        $bindings     = ['barber' => $barberId, 'date' => $date];

        foreach ($statuses as $index => $status) {
            $placeholders[]          = ':st' . $index;
            $bindings['st' . $index] = $status;
        }

        $sql = "SELECT id, start_time, end_time, duration_minutes
                FROM {$this->table}
                WHERE barber_id = :barber
                  AND booking_date = :date
                  AND status IN (" . implode(', ', $placeholders) . ')';

        if ($excludeId !== null) {
            $sql                  .= ' AND id != :exclude';
            $bindings['exclude']   = $excludeId;
        }

        $sql .= ' ORDER BY start_time';

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return $this->db()->select($sql, $bindings);
    }

    /** Listado paginado con filtros para administración/recepción (spec §89). */
    public function paginateFiltered(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['search'])) {
            $where[] = "(bk.public_code LIKE :search
                         OR CONCAT(c.first_name, ' ', c.last_name) LIKE :searchName
                         OR c.rut_normalized LIKE :searchRut
                         OR c.phone LIKE :searchPhone)";
            $like                    = '%' . $filters['search'] . '%';
            $bindings['search']      = $like;
            $bindings['searchName']  = $like;
            $bindings['searchRut']   = '%' . \App\Support\Rut::clean($filters['search']) . '%';
            $bindings['searchPhone'] = $like;
        }

        foreach ([
            'barber_id'      => 'bk.barber_id',
            'service_id'     => 'bk.service_id',
            'status'         => 'bk.status',
            'payment_status' => 'bk.payment_status',
            'source'         => 'bk.source',
            'customer_id'    => 'bk.customer_id',
            'branch_id'      => 'bk.branch_id',
        ] as $filter => $column) {
            if (isset($filters[$filter]) && $filters[$filter] !== '' && $filters[$filter] !== null) {
                $where[]              = "{$column} = :{$filter}";
                $bindings[$filter]    = $filters[$filter];
            }
        }

        if (!empty($filters['date_from'])) {
            $where[]               = 'bk.booking_date >= :dateFrom';
            $bindings['dateFrom']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]             = 'bk.booking_date <= :dateTo';
            $bindings['dateTo']  = $filters['date_to'];
        }
        if (($filters['upcoming'] ?? '') === '1') {
            $where[] = 'TIMESTAMP(bk.booking_date, bk.start_time) >= NOW()';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM bookings bk
             INNER JOIN customers c ON c.id = bk.customer_id
             WHERE {$whereSql}",
            $bindings
        );

        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);
        $order   = ($filters['order'] ?? 'desc') === 'asc'
            ? 'bk.booking_date ASC, bk.start_time ASC'
            : 'bk.booking_date DESC, bk.start_time DESC';

        $rows = $this->db()->select(
            self::SELECT_FULL . " WHERE {$whereSql} ORDER BY {$order}
             LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'data'      => $this->hideMany($rows),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** Próximas reservas (dashboards). */
    public function upcoming(int $limit = 10, ?int $barberId = null, ?int $branchId = null): array
    {
        $sql = self::SELECT_FULL . " WHERE TIMESTAMP(bk.booking_date, bk.start_time) >= NOW()
                                       AND bk.status IN ('pending','confirmed','checked_in')";
        $bindings = [];

        if ($barberId !== null) {
            $sql               .= ' AND bk.barber_id = :barber';
            $bindings['barber'] = $barberId;
        }
        if ($branchId !== null) {
            $sql               .= ' AND bk.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select(
            $sql . ' ORDER BY bk.booking_date, bk.start_time LIMIT ' . (int) $limit,
            $bindings
        );
    }

    /** Reservas pendientes de recordatorio (usado por el cron de Etapa 2). */
    public function pendingReminders(string $column, string $from, string $to): array
    {
        $column = $column === 'reminder_2h_sent' ? 'reminder_2h_sent' : 'reminder_24h_sent';

        return $this->db()->select(
            self::SELECT_FULL . " WHERE bk.{$column} = 0
                                    AND bk.status IN ('pending','confirmed')
                                    AND TIMESTAMP(bk.booking_date, bk.start_time) BETWEEN :from AND :to
                                  ORDER BY bk.booking_date, bk.start_time",
            ['from' => $from, 'to' => $to]
        );
    }

    public function markReminderSent(int $bookingId, string $column): void
    {
        $column = $column === 'reminder_2h_sent' ? 'reminder_2h_sent' : 'reminder_24h_sent';

        $this->db()->statement(
            "UPDATE {$this->table} SET {$column} = 1 WHERE id = :id",
            ['id' => $bookingId]
        );
    }

    public function customerFullName(array $booking): string
    {
        return trim(($booking['customer_first_name'] ?? '') . ' ' . ($booking['customer_last_name'] ?? ''));
    }
}
