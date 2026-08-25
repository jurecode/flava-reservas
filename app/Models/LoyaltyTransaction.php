<?php
/**
 * Ruta: /app/Models/LoyaltyTransaction.php
 * Fidelización basada en movimientos, nunca en un saldo suelto (spec §46).
 */

namespace App\Models;

use Core\Model;

class LoyaltyTransaction extends Model
{
    protected string $table = 'loyalty_transactions';
    protected bool $timestamps = false;

    protected array $fillable = [
        'customer_id', 'points', 'type', 'reference_type', 'reference_id',
        'description', 'created_by', 'expires_at',
    ];

    /** Saldo real = suma de movimientos. */
    public function balance(int $customerId): int
    {
        return (int) $this->db()->scalar(
            "SELECT COALESCE(SUM(points), 0) FROM {$this->table} WHERE customer_id = :id",
            ['id' => $customerId]
        );
    }

    public function forCustomer(int $customerId, int $limit = 50): array
    {
        return $this->where(['customer_id' => $customerId], 'created_at DESC', $limit);
    }
}
