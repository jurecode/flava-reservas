<?php
/**
 * Ruta: /app/Models/Deployment.php
 * Historial de despliegues del panel SUPER_ADMIN (spec §128).
 */

namespace App\Models;

use Core\Model;

class Deployment extends Model
{
    protected string $table = 'deployments';
    protected bool $timestamps = false;

    protected array $fillable = [
        'version', 'commit_hash', 'previous_commit', 'branch', 'strategy', 'status',
        'backup_path', 'migrations_run', 'notes', 'error_message', 'started_by',
        'started_at', 'finished_at',
    ];

    public const PENDING     = 'pending';
    public const RUNNING     = 'running';
    public const SUCCESS     = 'success';
    public const FAILED      = 'failed';
    public const ROLLED_BACK = 'rolled_back';

    private const LABELS = [
        self::PENDING     => 'Pendiente',
        self::RUNNING     => 'En ejecución',
        self::SUCCESS     => 'Exitoso',
        self::FAILED      => 'Fallido',
        self::ROLLED_BACK => 'Revertido',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::LABELS[(string) $status] ?? '—';
    }

    public static function statusBadge(?string $status): string
    {
        return match ((string) $status) {
            self::SUCCESS     => 'badge-completed',
            self::FAILED      => 'badge-noshow',
            self::RUNNING     => 'badge-progress',
            self::ROLLED_BACK => 'badge-pending',
            default           => 'badge-muted',
        };
    }

    public function start(array $data): int
    {
        return $this->create($data + [
            'status'     => self::RUNNING,
            'started_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function finish(int $id, string $status, array $extra = []): void
    {
        $this->update($id, $extra + [
            'status'      => $status,
            'finished_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function history(int $limit = 25): array
    {
        return $this->db()->select(
            "SELECT d.*, u.first_name, u.last_name
             FROM {$this->table} d
             LEFT JOIN users u ON u.id = d.started_by
             ORDER BY d.started_at DESC
             LIMIT " . (int) $limit
        );
    }

    public function lastSuccessful(): ?array
    {
        return $this->db()->selectOne(
            "SELECT * FROM {$this->table} WHERE status = :s ORDER BY started_at DESC LIMIT 1",
            ['s' => self::SUCCESS]
        );
    }

    /** ¿Hay un despliegue en curso? Evita ejecutar dos a la vez. */
    public function isRunning(): bool
    {
        return (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE status = :s AND started_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)",
            ['s' => self::RUNNING]
        ) > 0;
    }
}
