<?php
/**
 * Ruta: /app/Services/SettingService.php
 * Puente entre la configuración estática (/config) y la editable (tabla settings).
 * Orden de resolución: settings (BD) → config/*.php → valor por defecto.
 */

namespace App\Services;

use App\Models\Setting;
use App\Support\Crypto;

final class SettingService
{
    /** @var array<string,array>|null cache en memoria por request */
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            self::$cache = (new Setting())->allKeyed();
        } catch (\Throwable $e) {
            // Antes de instalar la base de datos el sistema debe seguir funcionando.
            logger()->warning('No se pudo leer la tabla settings', ['error' => $e->getMessage()]);
            self::$cache = [];
        }

        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** Valor tipado de una configuración. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::load()[$key] ?? null;

        if ($row === null) {
            return self::fromConfig($key, $default);
        }

        if ($row['type'] === 'secret') {
            // Los secretos no se entregan por esta vía (usar getSecret()).
            return $default;
        }

        $value = $row['value'];

        if ($value === null || $value === '') {
            return self::fromConfig($key, $default);
        }

        return match ($row['type']) {
            'integer' => (int) $value,
            'boolean' => (bool) ((int) $value),
            'json'    => json_decode((string) $value, true) ?? $default,
            default   => $value,
        };
    }

    /** Devuelve un secreto descifrado. Sólo para uso interno del servidor. */
    public static function getSecret(string $key): ?string
    {
        $row = self::load()[$key] ?? null;

        if ($row === null || ($row['value'] ?? '') === '') {
            return null;
        }

        return Crypto::decrypt((string) $row['value']);
    }

    /** Guarda un secreto cifrado + una pista no sensible para mostrar en el panel. */
    public static function putSecret(string $key, string $plain, ?int $userId = null, string $group = 'general'): void
    {
        (new Setting())->put($key, Crypto::encrypt($plain), 'secret', $userId, $group);
        (new Setting())->put($key . '_hint', Crypto::mask($plain), 'string', $userId, $group);
        self::flush();
    }

    public static function forgetSecret(string $key, ?int $userId = null): void
    {
        (new Setting())->put($key, '', 'secret', $userId);
        (new Setting())->put($key . '_hint', '', 'string', $userId);
        self::flush();
    }

    public static function set(string $key, mixed $value, ?int $userId = null, string $group = 'general'): void
    {
        (new Setting())->put($key, $value, null, $userId, $group);
        self::flush();
    }

    /** Guarda un grupo completo desde un formulario. */
    public static function setMany(array $values, ?int $userId = null, string $group = 'general'): void
    {
        $model = new Setting();

        foreach ($values as $key => $value) {
            $model->put((string) $key, $value, null, $userId, $group);
        }

        self::flush();
    }

    /** Fallback: /config/app.php usando el mapeo conocido de claves. */
    private static function fromConfig(string $key, mixed $default): mixed
    {
        $map = [
            'slot_interval'            => 'app.booking.slot_interval',
            'min_advance_minutes'      => 'app.booking.min_advance_minutes',
            'max_advance_days'         => 'app.booking.max_advance_days',
            'buffer_minutes'           => 'app.booking.buffer_minutes',
            'cancel_limit_hours'       => 'app.booking.cancel_limit_hours',
            'reschedule_limit_hours'   => 'app.booking.reschedule_limit_hours',
            'booking_reminder_hours_1' => 'app.booking.reminder_hours_1',
            'booking_reminder_hours_2' => 'app.booking.reminder_hours_2',
            'business_name'            => 'app.name',
            'github_branch'            => 'github.branch',
            'github_owner'             => 'github.owner',
            'github_repository'        => 'github.repository',
            'github_enabled'           => 'github.enabled',
        ];

        return isset($map[$key]) ? config($map[$key], $default) : $default;
    }

    /** Datos de negocio para las vistas públicas. */
    public static function business(): array
    {
        return [
            'name'      => self::get('business_name', config('app.name')),
            'tagline'   => self::get('business_tagline', 'Tu estilo. Tu momento.'),
            'email'     => self::get('business_email', 'hola@flava.cl'),
            'phone'     => self::get('business_phone', ''),
            'whatsapp'  => self::get('business_whatsapp', ''),
            'address'   => self::get('business_address', ''),
            'instagram' => self::get('business_instagram', ''),
            'maps_url'  => self::get('business_maps_url', ''),
            'logo'      => self::get('business_logo', ''),
            'policy'    => self::get('booking_policy', ''),
        ];
    }
}
