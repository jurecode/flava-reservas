<?php
/**
 * Ruta: /app/Models/BlockedTime.php
 * Bloqueos de agenda: almuerzo, vacaciones, permisos, cierre del local.
 */

namespace App\Models;

use Core\Model;

class BlockedTime extends Model
{
    protected string $table = 'blocked_times';

    protected array $fillable = [
        'branch_id', 'barber_id', 'start_datetime', 'end_datetime',
        'type', 'reason', 'is_recurring', 'created_by',
    ];

    public const TYPES = [
        'lunch'      => 'Almuerzo',
        'vacation'   => 'Vacaciones',
        'permission' => 'Permiso',
        'training'   => 'Capacitación',
        'day_off'    => 'Día libre',
        'holiday'    => 'Feriado / cierre',
        'manual'     => 'Bloqueo manual',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[(string) $type] ?? 'Bloqueo';
    }

    /**
     * Bloqueos que afectan a un barbero en una fecha.
     * Incluye los de sucursal (barber_id NULL).
     */
    public function forBarberOnDate(int $barberId, string $date, int $branchId): array
    {
        return $this->db()->select(
            "SELECT * FROM {$this->table}
             WHERE (barber_id = :barber OR (barber_id IS NULL AND branch_id = :branch))
               AND start_datetime < :dayEnd
               AND end_datetime   > :dayStart
             ORDER BY start_datetime",
            [
                'barber'   => $barberId,
                'branch'   => $branchId,
                'dayStart' => $date . ' 00:00:00',
                'dayEnd'   => $date . ' 23:59:59',
            ]
        );
    }

    /** Bloqueos de un rango de fechas para el calendario administrativo. */
    public function forRange(string $from, string $to, ?int $barberId = null, ?int $branchId = null): array
    {
        $sql = "SELECT bt.*, b.display_name AS barber_name, b.color AS barber_color,
                       u.first_name AS created_by_name
                FROM {$this->table} bt
                LEFT JOIN barbers b ON b.id = bt.barber_id
                LEFT JOIN users u   ON u.id = bt.created_by
                WHERE bt.start_datetime < :to AND bt.end_datetime > :from";

        $bindings = ['from' => $from . ' 00:00:00', 'to' => $to . ' 23:59:59'];

        if ($barberId !== null) {
            $sql               .= ' AND (bt.barber_id = :barber OR bt.barber_id IS NULL)';
            $bindings['barber'] = $barberId;
        }
        if ($branchId !== null) {
            $sql               .= ' AND bt.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select($sql . ' ORDER BY bt.start_datetime', $bindings);
    }

    public function upcoming(int $limit = 20, ?int $barberId = null): array
    {
        $sql = "SELECT bt.*, b.display_name AS barber_name
                FROM {$this->table} bt
                LEFT JOIN barbers b ON b.id = bt.barber_id
                WHERE bt.end_datetime >= NOW()";

        $bindings = [];

        if ($barberId !== null) {
            $sql               .= ' AND (bt.barber_id = :barber OR bt.barber_id IS NULL)';
            $bindings['barber'] = $barberId;
        }

        return $this->db()->select($sql . ' ORDER BY bt.start_datetime LIMIT ' . (int) $limit, $bindings);
    }

    /** ¿Hay reservas activas dentro del rango que se pretende bloquear? */
    public function conflictingBookings(?int $barberId, string $start, string $end): array
    {
        $sql = "SELECT b.id, b.public_code, b.booking_date, b.start_time, b.end_time,
                       c.first_name, c.last_name
                FROM bookings b
                INNER JOIN customers c ON c.id = b.customer_id
                WHERE b.status NOT IN ('cancelled','no_show')
                  AND TIMESTAMP(b.booking_date, b.start_time) < :end
                  AND TIMESTAMP(b.booking_date, b.end_time)   > :start";

        $bindings = ['start' => $start, 'end' => $end];

        if ($barberId !== null) {
            $sql               .= ' AND b.barber_id = :barber';
            $bindings['barber'] = $barberId;
        }

        return $this->db()->select($sql . ' ORDER BY b.booking_date, b.start_time', $bindings);
    }
}
