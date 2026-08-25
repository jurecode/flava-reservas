<?php
/**
 * Ruta: /app/Models/Branch.php
 * Sucursales. El MVP opera con una ("Flava Studio Principal") pero toda la
 * arquitectura ya está preparada para varias (spec §48).
 */

namespace App\Models;

use Core\Model;

class Branch extends Model
{
    protected string $table = 'branches';

    protected array $fillable = [
        'name', 'slug', 'address', 'commune', 'city', 'phone', 'whatsapp',
        'email', 'maps_url', 'latitude', 'longitude', 'timezone', 'is_default', 'status',
    ];

    private static ?array $defaultCache = null;

    /** Sucursal por defecto: usada en todo el MVP. */
    public function default(): array
    {
        if (self::$defaultCache !== null) {
            return self::$defaultCache;
        }

        $branch = $this->db()->selectOne(
            "SELECT * FROM {$this->table} WHERE is_default = 1 AND status = 1 ORDER BY id LIMIT 1"
        ) ?? $this->db()->selectOne("SELECT * FROM {$this->table} ORDER BY id LIMIT 1");

        return self::$defaultCache = ($branch ?? ['id' => 1, 'name' => config('app.name')]);
    }

    public static function defaultId(): int
    {
        return (int) (new self())->default()['id'];
    }

    public function active(): array
    {
        return $this->where(['status' => 1], 'name');
    }
}
