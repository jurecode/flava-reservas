<?php
/**
 * Ruta: /app/Models/Product.php
 * Preparado para la futura tienda (spec §44). Fuera del MVP.
 */

namespace App\Models;

use Core\Model;

class Product extends Model
{
    protected string $table = 'products';

    protected array $fillable = [
        'branch_id', 'category_id', 'name', 'slug', 'sku', 'description',
        'price', 'sale_price', 'cost', 'stock', 'image', 'status',
    ];

    public function available(): array
    {
        return $this->db()->select(
            "SELECT p.*, c.name AS category_name
             FROM {$this->table} p
             LEFT JOIN product_categories c ON c.id = p.category_id
             WHERE p.status = 1 AND p.stock > 0
             ORDER BY p.name"
        );
    }

    public function effectivePrice(array $product): float
    {
        return $product['sale_price'] !== null && (float) $product['sale_price'] > 0
            ? (float) $product['sale_price']
            : (float) $product['price'];
    }
}
