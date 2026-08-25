<?php
/**
 * Ruta: /app/Models/Notification.php
 * Cola de notificaciones (spec §42). El envío real llega en la Etapa 2 mediante
 * un cron que procesa las pendientes.
 */

namespace App\Models;

use Core\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    protected array $fillable = [
        'customer_id', 'booking_id', 'type', 'channel', 'recipient', 'subject',
        'payload', 'status', 'attempts', 'scheduled_at', 'sent_at', 'error_message',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Próximas notificaciones a procesar. */
    public function due(int $limit = 50): array
    {
        return $this->db()->select(
            "SELECT * FROM {$this->table}
             WHERE status = 'pending' AND scheduled_at <= NOW() AND attempts < 3
             ORDER BY scheduled_at
             LIMIT " . (int) $limit
        );
    }

    public function markSent(int $id): void
    {
        $this->update($id, [
            'status'  => self::STATUS_SENT,
            'sent_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db()->statement(
            "UPDATE {$this->table}
             SET status = IF(attempts + 1 >= 3, 'failed', 'pending'),
                 attempts = attempts + 1,
                 error_message = :error,
                 updated_at = NOW()
             WHERE id = :id",
            ['id' => $id, 'error' => mb_substr($error, 0, 255)]
        );
    }

    /** Cancela las notificaciones futuras de una reserva (al cancelarla/reprogramarla). */
    public function cancelPendingForBooking(int $bookingId, array $types = []): int
    {
        $sql      = "UPDATE {$this->table} SET status = 'cancelled', updated_at = NOW()
                     WHERE booking_id = :id AND status = 'pending'";
        $bindings = ['id' => $bookingId];

        if ($types !== []) {
            $placeholders = [];
            foreach (array_values($types) as $index => $type) {
                $placeholders[]          = ':t' . $index;
                $bindings['t' . $index]  = $type;
            }
            $sql .= ' AND type IN (' . implode(', ', $placeholders) . ')';
        }

        return $this->db()->statement($sql, $bindings);
    }

    public function paginateFiltered(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $conditions = [];

        foreach (['status', 'channel', 'type'] as $key) {
            if (!empty($filters[$key])) {
                $conditions[$key] = $filters[$key];
            }
        }

        return $this->paginate($conditions, $page, $perPage, 'scheduled_at DESC');
    }

    public function stats(): array
    {
        $row = $this->db()->selectOne(
            "SELECT
                SUM(status = 'pending')   AS pending,
                SUM(status = 'sent')      AS sent,
                SUM(status = 'failed')    AS failed,
                SUM(status = 'cancelled') AS cancelled
             FROM {$this->table}"
        );

        return array_map('intval', $row ?? []);
    }
}
