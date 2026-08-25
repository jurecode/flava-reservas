<?php
/**
 * Ruta: /app/Models/BookingStatusHistory.php
 * Traza de cambios de estado (spec §61): no dependemos sólo del estado actual.
 */

namespace App\Models;

use Core\Model;

class BookingStatusHistory extends Model
{
    protected string $table = 'booking_status_history';
    protected bool $timestamps = false;

    protected array $fillable = ['booking_id', 'old_status', 'new_status', 'note', 'changed_by'];

    public function record(int $bookingId, ?string $oldStatus, string $newStatus, ?int $userId = null, ?string $note = null): void
    {
        $this->create([
            'booking_id' => $bookingId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note'       => $note,
            'changed_by' => $userId,
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function forBooking(int $bookingId): array
    {
        return $this->db()->select(
            "SELECT h.*, u.first_name, u.last_name, u.role
             FROM {$this->table} h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.booking_id = :id
             ORDER BY h.created_at ASC, h.id ASC",
            ['id' => $bookingId]
        );
    }
}
