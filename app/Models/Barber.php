<?php
/**
 * Ruta: /app/Models/Barber.php
 */

namespace App\Models;

use Core\Model;

class Barber extends Model
{
    protected string $table = 'barbers';

    protected array $fillable = [
        'user_id', 'branch_id', 'first_name', 'last_name', 'display_name', 'slug',
        'email', 'phone', 'photo', 'bio', 'specialty', 'instagram', 'color',
        'accepts_online', 'sort_order', 'status',
    ];

    /** Barberos visibles en el sitio público. */
    public function publicList(?int $branchId = null): array
    {
        $sql      = "SELECT * FROM {$this->table} WHERE status = 1 AND accepts_online = 1";
        $bindings = [];

        if ($branchId !== null) {
            $sql                  .= ' AND branch_id = :branch';
            $bindings['branch']    = $branchId;
        }

        return $this->db()->select($sql . ' ORDER BY sort_order, display_name', $bindings);
    }

    public function activeList(?int $branchId = null): array
    {
        $sql      = "SELECT * FROM {$this->table} WHERE status = 1";
        $bindings = [];

        if ($branchId !== null) {
            $sql               .= ' AND branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select($sql . ' ORDER BY sort_order, display_name', $bindings);
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /** Barberos que pueden realizar un servicio (y aceptan reservas online). */
    public function availableForService(int $serviceId, bool $onlineOnly = true): array
    {
        $sql = "SELECT b.*, bs.custom_price, bs.custom_duration
                FROM {$this->table} b
                INNER JOIN barber_services bs ON bs.barber_id = b.id
                WHERE bs.service_id = :sid AND b.status = 1";

        if ($onlineOnly) {
            $sql .= ' AND b.accepts_online = 1';
        }

        return $this->db()->select($sql . ' ORDER BY b.sort_order, b.display_name', ['sid' => $serviceId]);
    }

    /** @return array<int,int> ids de los servicios asignados */
    public function serviceIds(int $barberId): array
    {
        return array_map(
            'intval',
            array_column(
                $this->db()->select('SELECT service_id FROM barber_services WHERE barber_id = :b', ['b' => $barberId]),
                'service_id'
            )
        );
    }

    public function servicesWithPricing(int $barberId): array
    {
        return $this->db()->select(
            "SELECT s.*, bs.custom_price, bs.custom_duration
             FROM services s
             INNER JOIN barber_services bs ON bs.service_id = s.id
             WHERE bs.barber_id = :b AND s.status = 1
             ORDER BY s.sort_order, s.name",
            ['b' => $barberId]
        );
    }

    /** Reemplaza el conjunto de servicios del barbero. */
    public function syncServices(int $barberId, array $serviceIds, array $customs = []): void
    {
        $this->db()->transaction(function () use ($barberId, $serviceIds, $customs): void {
            $this->db()->delete('barber_services', 'barber_id = :b', ['b' => $barberId]);

            foreach (array_unique(array_map('intval', $serviceIds)) as $serviceId) {
                if ($serviceId <= 0) {
                    continue;
                }

                $this->db()->insert('barber_services', [
                    'barber_id'       => $barberId,
                    'service_id'      => $serviceId,
                    'custom_price'    => $customs[$serviceId]['price'] ?? null,
                    'custom_duration' => $customs[$serviceId]['duration'] ?? null,
                ]);
            }
        });
    }

    public function listWithStats(array $filters = []): array
    {
        $sql = "SELECT b.*, u.email AS user_email, u.status AS user_status, br.name AS branch_name,
                       (SELECT COUNT(*) FROM barber_services bs WHERE bs.barber_id = b.id) AS services_count,
                       (SELECT COUNT(*) FROM bookings bk
                         WHERE bk.barber_id = b.id AND bk.booking_date = CURDATE()
                           AND bk.status NOT IN ('cancelled','no_show')) AS bookings_today
                FROM {$this->table} b
                LEFT JOIN users u    ON u.id = b.user_id
                LEFT JOIN branches br ON br.id = b.branch_id
                WHERE 1 = 1";

        $bindings = [];

        if (!empty($filters['search'])) {
            $sql               .= ' AND (b.display_name LIKE :search OR b.first_name LIKE :search OR b.last_name LIKE :search)';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $sql               .= ' AND b.status = :status';
            $bindings['status'] = (int) $filters['status'];
        }
        if (!empty($filters['branch_id'])) {
            $sql                  .= ' AND b.branch_id = :branch';
            $bindings['branch']    = (int) $filters['branch_id'];
        }

        return $this->db()->select($sql . ' ORDER BY b.sort_order, b.display_name', $bindings);
    }

    public function fullName(array $barber): string
    {
        return trim(($barber['first_name'] ?? '') . ' ' . ($barber['last_name'] ?? ''));
    }
}
