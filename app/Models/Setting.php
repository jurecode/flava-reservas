<?php
/**
 * Ruta: /app/Models/Setting.php
 * Configuración editable desde el panel. Los valores de tipo `secret` se
 * guardan cifrados (App\Support\Crypto) y nunca se devuelven en claro.
 */

namespace App\Models;

use Core\Model;

class Setting extends Model
{
    protected string $table = 'settings';

    protected array $fillable = [
        'group_name', 'key_name', 'value', 'type', 'label', 'description', 'is_public', 'updated_by',
    ];

    public function allKeyed(): array
    {
        $result = [];

        foreach ($this->all('group_name, key_name') as $row) {
            $result[$row['key_name']] = $row;
        }

        return $result;
    }

    public function byGroup(string $group): array
    {
        return $this->where(['group_name' => $group], 'key_name');
    }

    public function findByKey(string $key): ?array
    {
        return $this->findBy('key_name', $key);
    }

    /** Inserta o actualiza sin perder el tipo declarado. */
    public function put(string $key, mixed $value, ?string $type = null, ?int $userId = null, string $group = 'general'): void
    {
        $existing = $this->findByKey($key);
        $type   ??= $existing['type'] ?? 'string';

        if ($type === 'json' && !is_string($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if ($type === 'boolean') {
            $value = $value ? '1' : '0';
        }

        if ($existing === null) {
            $this->create([
                'group_name' => $group,
                'key_name'   => $key,
                'value'      => (string) $value,
                'type'       => $type,
                'updated_by' => $userId,
            ]);

            return;
        }

        $this->update((int) $existing['id'], [
            'value'      => (string) $value,
            'updated_by' => $userId,
        ]);
    }

    /** Ajustes públicos (los que puede consumir el sitio del cliente). */
    public function publicSettings(): array
    {
        $result = [];

        foreach ($this->where(['is_public' => 1]) as $row) {
            if ($row['type'] === 'secret') {
                continue;
            }
            $result[$row['key_name']] = $row['value'];
        }

        return $result;
    }
}
