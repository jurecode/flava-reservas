<?php
/**
 * Ruta: /app/Models/Payment.php
 */

namespace App\Models;

use App\Support\PaymentStatus;
use Core\Model;

class Payment extends Model
{
    protected string $table = 'payments';

    protected array $fillable = [
        'booking_id', 'order_id', 'customer_id', 'amount', 'payment_method', 'status',
        'provider', 'transaction_id', 'metadata', 'notes', 'registered_by',
        'paid_at', 'refunded_at',
    ];

    public function forBooking(int $bookingId): array
    {
        return $this->db()->select(
            "SELECT p.*, u.first_name AS registered_by_name
             FROM {$this->table} p
             LEFT JOIN users u ON u.id = p.registered_by
             WHERE p.booking_id = :id
             ORDER BY p.created_at DESC",
            ['id' => $bookingId]
        );
    }

    /** Total efectivamente pagado de una reserva. */
    public function paidTotal(int $bookingId): float
    {
        return (float) $this->db()->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->table}
             WHERE booking_id = :id AND status = :status",
            ['id' => $bookingId, 'status' => PaymentStatus::PAID]
        );
    }

    public function paginateFiltered(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['status'])) {
            $where[]            = 'p.status = :status';
            $bindings['status'] = $filters['status'];
        }
        if (!empty($filters['payment_method'])) {
            $where[]            = 'p.payment_method = :method';
            $bindings['method'] = $filters['payment_method'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'DATE(COALESCE(p.paid_at, p.created_at)) >= :dateFrom';
            $bindings['dateFrom'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'DATE(COALESCE(p.paid_at, p.created_at)) <= :dateTo';
            $bindings['dateTo'] = $filters['date_to'];
        }
        if (!empty($filters['barber_id'])) {
            $where[]            = 'bk.barber_id = :barber';
            $bindings['barber'] = (int) $filters['barber_id'];
        }
        if (!empty($filters['search'])) {
            $where[]            = "(bk.public_code LIKE :search OR CONCAT(c.first_name,' ',c.last_name) LIKE :searchName)";
            $bindings['search']     = '%' . $filters['search'] . '%';
            $bindings['searchName'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $joins    = "FROM {$this->table} p
                     LEFT JOIN bookings  bk ON bk.id = p.booking_id
                     LEFT JOIN customers c  ON c.id = p.customer_id
                     LEFT JOIN barbers   b  ON b.id = bk.barber_id
                     LEFT JOIN users     u  ON u.id = p.registered_by";

        $total   = (int) $this->db()->scalar("SELECT COUNT(*) {$joins} WHERE {$whereSql}", $bindings);
        $sum     = (float) $this->db()->scalar("SELECT COALESCE(SUM(p.amount),0) {$joins} WHERE {$whereSql} AND p.status = 'paid'", $bindings);
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);

        $rows = $this->db()->select(
            "SELECT p.*, bk.public_code, bk.booking_date, b.display_name AS barber_name,
                    c.first_name AS customer_first_name, c.last_name AS customer_last_name,
                    u.first_name AS registered_by_name
             {$joins}
             WHERE {$whereSql}
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC
             LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'data'      => $rows,
            'total'     => $total,
            'sum_paid'  => $sum,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
