<?php
/**
 * Ruta: /app/Models/Order.php
 * Preparado para la futura tienda (spec §45). Fuera del MVP.
 */

namespace App\Models;

use Core\Model;

class Order extends Model
{
    protected string $table = 'orders';

    protected array $fillable = [
        'public_code', 'branch_id', 'customer_id', 'booking_id', 'subtotal',
        'discount', 'total', 'status', 'payment_method', 'notes', 'created_by',
    ];

    public function items(int $orderId): array
    {
        return $this->db()->select(
            'SELECT * FROM order_items WHERE order_id = :id ORDER BY id',
            ['id' => $orderId]
        );
    }
}
