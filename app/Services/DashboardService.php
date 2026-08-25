<?php
/**
 * Ruta: /app/Services/DashboardService.php
 * KPIs construidos siempre desde datos reales (spec §34).
 */

namespace App\Services;

use App\Models\Branch;
use App\Support\DateHelper;
use Core\Database;

final class DashboardService
{
    public function __construct(
        private readonly AvailabilityService $availability = new AvailabilityService(),
    ) {
    }

    private function db(): Database
    {
        return Database::instance();
    }

    /** Panel del administrador. */
    public function adminSummary(?int $branchId = null): array
    {
        $branchId ??= Branch::defaultId();
        $today      = today();
        $weekStart  = DateHelper::startOfWeek($today);
        $weekEnd    = DateHelper::endOfWeek($today);
        $monthStart = DateHelper::startOfMonth($today);

        return [
            'today'   => $this->periodStats($today, $today, $branchId),
            'week'    => $this->periodStats($weekStart, $weekEnd, $branchId),
            'month'   => $this->periodStats($monthStart, DateHelper::endOfMonth($today), $branchId),
            'occupancy_today' => $this->availability->branchOccupancy($today, $branchId),
            'top_barber'      => $this->topBarber($weekStart, $weekEnd, $branchId),
            'top_services'    => $this->topServices($monthStart, $today, $branchId),
            'busiest_hours'   => $this->busiestHours($monthStart, $today, $branchId),
            'revenue_series'  => $this->revenueSeries(14, $branchId),
            'new_customers'   => $this->newCustomers($monthStart, $today),
        ];
    }

    /**
     * Métricas de un rango de fechas.
     * @return array{bookings:int,completed:int,cancelled:int,no_show:int,revenue:float,customers:int,ticket:float,no_show_rate:float}
     */
    public function periodStats(string $from, string $to, ?int $branchId = null): array
    {
        $bindings = ['from' => $from, 'to' => $to];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        $row = $this->db()->selectOne(
            "SELECT
                COUNT(*)                                   AS bookings,
                SUM(status = 'completed')                  AS completed,
                SUM(status = 'cancelled')                  AS cancelled,
                SUM(status = 'no_show')                    AS no_show,
                COUNT(DISTINCT customer_id)                AS customers,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS revenue
             FROM bookings
             WHERE booking_date BETWEEN :from AND :to{$branchSql}",
            $bindings
        ) ?? [];

        $bookings  = (int) ($row['bookings'] ?? 0);
        $completed = (int) ($row['completed'] ?? 0);
        $noShow    = (int) ($row['no_show'] ?? 0);
        $revenue   = (float) ($row['revenue'] ?? 0);

        return [
            'bookings'     => $bookings,
            'completed'    => $completed,
            'cancelled'    => (int) ($row['cancelled'] ?? 0),
            'no_show'      => $noShow,
            'customers'    => (int) ($row['customers'] ?? 0),
            'revenue'      => $revenue,
            'ticket'       => $completed > 0 ? round($revenue / $completed) : 0.0,
            'no_show_rate' => $bookings > 0 ? round(($noShow / $bookings) * 100, 1) : 0.0,
        ];
    }

    /** Barbero con mayor ocupación del periodo. */
    public function topBarber(string $from, string $to, ?int $branchId = null): ?array
    {
        $bindings  = ['from' => $from, 'to' => $to];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND bk.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->selectOne(
            "SELECT b.id, b.display_name, b.photo, b.color,
                    COUNT(bk.id) AS bookings,
                    COALESCE(SUM(CASE WHEN bk.payment_status = 'paid' THEN bk.total ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(bk.duration_minutes), 0) AS minutes
             FROM barbers b
             INNER JOIN bookings bk ON bk.barber_id = b.id
                  AND bk.booking_date BETWEEN :from AND :to
                  AND bk.status NOT IN ('cancelled','no_show')
             WHERE b.status = 1{$branchSql}
             GROUP BY b.id
             ORDER BY bookings DESC
             LIMIT 1",
            $bindings
        );
    }

    /** Ranking de barberos del periodo (reportes). */
    public function barberRanking(string $from, string $to, ?int $branchId = null): array
    {
        $bindings  = ['from' => $from, 'to' => $to];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND b.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select(
            "SELECT b.id, b.display_name, b.color,
                    COUNT(bk.id) AS bookings,
                    SUM(bk.status = 'completed') AS completed,
                    SUM(bk.status = 'no_show')   AS no_show,
                    COALESCE(SUM(CASE WHEN bk.payment_status = 'paid' THEN bk.total ELSE 0 END), 0) AS revenue
             FROM barbers b
             LEFT JOIN bookings bk ON bk.barber_id = b.id AND bk.booking_date BETWEEN :from AND :to
             WHERE b.status = 1{$branchSql}
             GROUP BY b.id
             ORDER BY revenue DESC, bookings DESC",
            $bindings
        );
    }

    public function topServices(string $from, string $to, ?int $branchId = null, int $limit = 5): array
    {
        $bindings  = ['from' => $from, 'to' => $to];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND bk.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select(
            "SELECT bk.service_id, bk.service_name,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN bk.payment_status = 'paid' THEN bk.total ELSE 0 END), 0) AS revenue
             FROM bookings bk
             WHERE bk.booking_date BETWEEN :from AND :to
               AND bk.status NOT IN ('cancelled')
               {$branchSql}
             GROUP BY bk.service_id, bk.service_name
             ORDER BY bookings DESC
             LIMIT " . (int) $limit,
            $bindings
        );
    }

    /** Horas más solicitadas (spec §93). */
    public function busiestHours(string $from, string $to, ?int $branchId = null): array
    {
        $bindings  = ['from' => $from, 'to' => $to];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select(
            "SELECT HOUR(start_time) AS hour, COUNT(*) AS bookings
             FROM bookings
             WHERE booking_date BETWEEN :from AND :to
               AND status NOT IN ('cancelled')
               {$branchSql}
             GROUP BY HOUR(start_time)
             ORDER BY hour",
            $bindings
        );
    }

    /** Serie de ingresos de los últimos N días (gráfico del dashboard). */
    public function revenueSeries(int $days = 14, ?int $branchId = null): array
    {
        $from      = DateHelper::make("-{$days} days")->format('Y-m-d');
        $bindings  = ['from' => $from, 'to' => today()];
        $branchSql = '';

        if ($branchId !== null) {
            $branchSql          = ' AND branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        $rows = $this->db()->select(
            "SELECT booking_date,
                    COUNT(*) AS bookings,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS revenue
             FROM bookings
             WHERE booking_date BETWEEN :from AND :to{$branchSql}
             GROUP BY booking_date
             ORDER BY booking_date",
            $bindings
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['booking_date']] = $row;
        }

        $series = [];
        foreach (DateHelper::range($from, today()) as $date) {
            $series[] = [
                'date'     => $date,
                'label'    => DateHelper::shortEs($date),
                'bookings' => (int) ($indexed[$date]['bookings'] ?? 0),
                'revenue'  => (float) ($indexed[$date]['revenue'] ?? 0),
            ];
        }

        return $series;
    }

    public function newCustomers(string $from, string $to): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM customers WHERE DATE(created_at) BETWEEN :from AND :to',
            ['from' => $from, 'to' => $to]
        );
    }

    /** Panel de recepción: operativo, centrado en el día (spec §35). */
    public function receptionSummary(?int $branchId = null): array
    {
        $branchId ??= Branch::defaultId();
        $today      = today();

        $row = $this->db()->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status IN ('pending','confirmed')) AS pending,
                SUM(status = 'checked_in')  AS waiting,
                SUM(status = 'in_progress') AS in_progress,
                SUM(status = 'completed')   AS completed,
                SUM(payment_status = 'pending' AND status = 'completed') AS unpaid
             FROM bookings
             WHERE booking_date = :today AND branch_id = :branch AND status <> 'cancelled'",
            ['today' => $today, 'branch' => $branchId]
        ) ?? [];

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'pending'     => (int) ($row['pending'] ?? 0),
            'waiting'     => (int) ($row['waiting'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed'   => (int) ($row['completed'] ?? 0),
            'unpaid'      => (int) ($row['unpaid'] ?? 0),
        ];
    }

    /** Panel del barbero: sólo sus números. */
    public function barberSummary(int $barberId, ?string $date = null): array
    {
        $date ??= today();

        $row = $this->db()->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(status IN ('pending','confirmed')) AS upcoming,
                SUM(status = 'completed') AS completed,
                SUM(status = 'no_show')   AS no_show,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS revenue
             FROM bookings
             WHERE barber_id = :barber AND booking_date = :date AND status <> 'cancelled'",
            ['barber' => $barberId, 'date' => $date]
        ) ?? [];

        $weekStart = DateHelper::startOfWeek($date);

        return [
            'total'      => (int) ($row['total'] ?? 0),
            'upcoming'   => (int) ($row['upcoming'] ?? 0),
            'completed'  => (int) ($row['completed'] ?? 0),
            'no_show'    => (int) ($row['no_show'] ?? 0),
            'revenue'    => (float) ($row['revenue'] ?? 0),
            'occupancy'  => $this->availability->occupancyRate($barberId, $date),
            'week_total' => (int) $this->db()->scalar(
                "SELECT COUNT(*) FROM bookings
                 WHERE barber_id = :barber AND booking_date BETWEEN :from AND :to
                   AND status NOT IN ('cancelled','no_show')",
                ['barber' => $barberId, 'from' => $weekStart, 'to' => DateHelper::endOfWeek($date)]
            ),
        ];
    }

    /** Clientes frecuentes (spec §93). */
    public function frequentCustomers(int $limit = 10): array
    {
        return $this->db()->select(
            "SELECT id, first_name, last_name, phone, completed_bookings, total_spent, last_visit_at
             FROM customers
             WHERE completed_bookings > 0
             ORDER BY completed_bookings DESC, total_spent DESC
             LIMIT " . (int) $limit
        );
    }
}
