<?php
/**
 * Ruta: /app/Models/ServiceCategory.php
 */

namespace App\Models;

use Core\Model;

class ServiceCategory extends Model
{
    protected string $table = 'service_categories';

    protected array $fillable = ['name', 'slug', 'description', 'icon', 'sort_order', 'status'];

    public function active(): array
    {
        return $this->where(['status' => 1], 'sort_order, name');
    }

    /** Categorías que tienen al menos un servicio activo. */
    public function withServices(): array
    {
        return $this->db()->select(
            "SELECT c.*, COUNT(s.id) AS services_count
             FROM {$this->table} c
             LEFT JOIN services s ON s.category_id = c.id AND s.status = 1
             WHERE c.status = 1
             GROUP BY c.id
             HAVING services_count > 0
             ORDER BY c.sort_order, c.name"
        );
    }
}
