<?php
/**
 * Ruta: /app/Models/ActivityLog.php
 * Auditoría de operaciones importantes (spec §60).
 */

namespace App\Models;

use Core\Model;

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';
    protected bool $timestamps = false;

    protected array $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'description',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    public function paginateFiltered(array $filters = [], int $page = 1, int $perPage = 40): array
    {
        $where    = ['1 = 1'];
        $bindings = [];

        if (!empty($filters['user_id'])) {
            $where[]            = 'l.user_id = :user';
            $bindings['user']   = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[]            = 'l.action LIKE :action';
            $bindings['action'] = $filters['action'] . '%';
        }
        if (!empty($filters['entity_type'])) {
            $where[]            = 'l.entity_type = :entity';
            $bindings['entity'] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $where[]              = 'l.entity_id = :entityId';
            $bindings['entityId'] = (int) $filters['entity_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'l.created_at >= :dateFrom';
            $bindings['dateFrom'] = $filters['date_from'] . ' 00:00:00';
        }

        $whereSql = implode(' AND ', $where);
        $joins    = "FROM {$this->table} l LEFT JOIN users u ON u.id = l.user_id";

        $total   = (int) $this->db()->scalar("SELECT COUNT(*) {$joins} WHERE {$whereSql}", $bindings);
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);

        $rows = $this->db()->select(
            "SELECT l.*, u.first_name, u.last_name, u.role
             {$joins}
             WHERE {$whereSql}
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $bindings
        );

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function forEntity(string $type, int $id, int $limit = 30): array
    {
        return $this->db()->select(
            "SELECT l.*, u.first_name, u.last_name
             FROM {$this->table} l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.entity_type = :type AND l.entity_id = :id
             ORDER BY l.created_at DESC
             LIMIT " . (int) $limit,
            ['type' => $type, 'id' => $id]
        );
    }
}
