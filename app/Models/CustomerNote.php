<?php
/**
 * Ruta: /app/Models/CustomerNote.php
 * Notas de servicio (técnicas, visibles para el barbero) y administrativas.
 */

namespace App\Models;

use Core\Model;

class CustomerNote extends Model
{
    protected string $table = 'customer_notes';

    protected array $fillable = [
        'customer_id', 'booking_id', 'author_id', 'type', 'note', 'is_pinned',
    ];

    public const TYPE_SERVICE = 'service';
    public const TYPE_ADMIN   = 'admin';

    /**
     * @param string|null $type null = todas (sólo para ADMIN/RECEPTION).
     *                          El barbero recibe únicamente las de tipo 'service'.
     */
    public function forCustomer(int $customerId, ?string $type = null, int $limit = 50): array
    {
        $sql = "SELECT n.*, u.first_name AS author_first, u.last_name AS author_last,
                       bk.public_code, bk.booking_date
                FROM {$this->table} n
                LEFT JOIN users u     ON u.id = n.author_id
                LEFT JOIN bookings bk ON bk.id = n.booking_id
                WHERE n.customer_id = :id";

        $bindings = ['id' => $customerId];

        if ($type !== null) {
            $sql             .= ' AND n.type = :type';
            $bindings['type'] = $type;
        }

        return $this->db()->select(
            $sql . ' ORDER BY n.is_pinned DESC, n.created_at DESC LIMIT ' . (int) $limit,
            $bindings
        );
    }

    /** Última nota técnica: lo que el barbero necesita ver antes de atender. */
    public function lastServiceNote(int $customerId): ?array
    {
        return $this->db()->selectOne(
            "SELECT n.*, u.first_name AS author_first
             FROM {$this->table} n
             LEFT JOIN users u ON u.id = n.author_id
             WHERE n.customer_id = :id AND n.type = 'service'
             ORDER BY n.is_pinned DESC, n.created_at DESC
             LIMIT 1",
            ['id' => $customerId]
        );
    }
}
