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

    /**
     * Ubicación corta para mostrar en el sitio: «Providencia», «Viña del Mar».
     *
     * Usa la comuna, y si no está, la ciudad. Si tampoco, toma el último tramo
     * de la dirección, que en Chile suele ser justamente la comuna
     * («Av. Principal 1234, Providencia»). Devuelve null si no hay nada:
     * es preferible omitir el dato a inventarlo.
     */
    public static function locationLabel(?array $branch = null): ?string
    {
        $branch ??= (new self())->default();

        foreach (['commune', 'city'] as $campo) {
            $valor = trim((string) ($branch[$campo] ?? ''));

            if ($valor !== '') {
                return $valor;
            }
        }

        $direccion = trim((string) ($branch['address'] ?? ''));

        if ($direccion === '' || !str_contains($direccion, ',')) {
            return null;
        }

        $tramos = array_map('trim', explode(',', $direccion));
        $ultimo = end($tramos);

        // Un tramo con números es parte de la calle, no una comuna
        return ($ultimo !== '' && !preg_match('/\d/', $ultimo)) ? $ultimo : null;
    }
}
