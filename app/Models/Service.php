<?php
/**
 * Ruta: /app/Models/Service.php
 */

namespace App\Models;

use Core\Model;

class Service extends Model
{
    protected string $table = 'services';

    protected array $fillable = [
        'category_id', 'name', 'slug', 'description', 'price', 'duration_minutes',
        'buffer_minutes', 'image', 'color', 'sort_order', 'is_featured',
        'online_bookable', 'status',
    ];

    /** Servicios ofrecidos en el booking público. */
    public function bookable(): array
    {
        return $this->db()->select(
            "SELECT s.*, c.name AS category_name
             FROM {$this->table} s
             LEFT JOIN service_categories c ON c.id = s.category_id
             WHERE s.status = 1 AND s.online_bookable = 1
             ORDER BY s.sort_order, s.name"
        );
    }

    /** Todos los activos (incluye los no publicables online: uso interno). */
    public function activeAll(): array
    {
        return $this->db()->select(
            "SELECT s.*, c.name AS category_name
             FROM {$this->table} s
             LEFT JOIN service_categories c ON c.id = s.category_id
             WHERE s.status = 1
             ORDER BY s.sort_order, s.name"
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function listWithCategory(array $filters = []): array
    {
        $sql      = "SELECT s.*, c.name AS category_name,
                            (SELECT COUNT(*) FROM barber_services bs WHERE bs.service_id = s.id) AS barbers_count
                     FROM {$this->table} s
                     LEFT JOIN service_categories c ON c.id = s.category_id
                     WHERE 1 = 1";
        $bindings = [];

        if (!empty($filters['search'])) {
            $sql               .= ' AND s.name LIKE :search';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['category_id'])) {
            $sql                    .= ' AND s.category_id = :category_id';
            $bindings['category_id'] = (int) $filters['category_id'];
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql               .= ' AND s.status = :status';
            $bindings['status'] = (int) $filters['status'];
        }

        $sql .= ' ORDER BY s.sort_order, s.name';

        return $this->db()->select($sql, $bindings);
    }

    /** Barberos habilitados para este servicio. */
    public function barbers(int $serviceId): array
    {
        return $this->db()->select(
            "SELECT b.*, bs.custom_price, bs.custom_duration
             FROM barbers b
             INNER JOIN barber_services bs ON bs.barber_id = b.id
             WHERE bs.service_id = :sid AND b.status = 1
             ORDER BY b.sort_order, b.display_name",
            ['sid' => $serviceId]
        );
    }

    /**
     * Precio y duración efectivos considerando la personalización del barbero.
     *
     * @return array{price:float,duration:int}
     */
    public function effectiveFor(array $service, ?int $barberId = null): array
    {
        $price    = (float) $service['price'];
        $duration = (int) $service['duration_minutes'];

        if ($barberId !== null) {
            $custom = $this->db()->selectOne(
                'SELECT custom_price, custom_duration FROM barber_services WHERE barber_id = :b AND service_id = :s',
                ['b' => $barberId, 's' => (int) $service['id']]
            );

            if ($custom !== null) {
                $price    = $custom['custom_price'] !== null ? (float) $custom['custom_price'] : $price;
                $duration = $custom['custom_duration'] !== null ? (int) $custom['custom_duration'] : $duration;
            }
        }

        return ['price' => $price, 'duration' => $duration];
    }
}
