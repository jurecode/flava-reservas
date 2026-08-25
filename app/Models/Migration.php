<?php
/**
 * Ruta: /app/Models/Migration.php
 * Registro de migraciones ejecutadas (spec §123): cada archivo corre una sola vez.
 */

namespace App\Models;

use Core\Model;

class Migration extends Model
{
    protected string $table = 'migrations';
    protected bool $timestamps = false;

    protected array $fillable = ['migration', 'batch', 'checksum', 'executed_at'];

    /** @return array<int,string> */
    public function executedNames(): array
    {
        return array_column($this->db()->select("SELECT migration FROM {$this->table}"), 'migration');
    }

    public function wasExecuted(string $name): bool
    {
        return (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM {$this->table} WHERE migration = :m",
            ['m' => $name]
        ) > 0;
    }

    public function nextBatch(): int
    {
        return ((int) $this->db()->scalar("SELECT COALESCE(MAX(batch), 0) FROM {$this->table}")) + 1;
    }

    public function record(string $name, int $batch, string $checksum): void
    {
        $this->create([
            'migration'   => $name,
            'batch'       => $batch,
            'checksum'    => $checksum,
            'executed_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function history(int $limit = 50): array
    {
        return $this->db()->select(
            "SELECT * FROM {$this->table} ORDER BY executed_at DESC, id DESC LIMIT " . (int) $limit
        );
    }
}
