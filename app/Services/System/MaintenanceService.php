<?php
/**
 * Ruta: /app/Services/System/MaintenanceService.php
 * Modo mantención mediante archivo bandera en /storage/framework (spec §127).
 */

namespace App\Services\System;

use App\Services\ActivityLogger;
use Core\Auth;

final class MaintenanceService
{
    private string $file;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? (string) config('app.maintenance_file');
    }

    public function isEnabled(): bool
    {
        return is_file($this->file);
    }

    public function enable(?string $message = null, ?int $estimatedMinutes = null): bool
    {
        $directory = dirname($this->file);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $payload = json_encode([
            'enabled_at' => now()->format('Y-m-d H:i:s'),
            'by'         => Auth::displayName() ?: 'sistema',
            'message'    => $message ?: 'Estamos realizando una actualización. Volveremos en unos minutos.',
            'minutes'    => $estimatedMinutes,
        ], JSON_UNESCAPED_UNICODE);

        $written = @file_put_contents($this->file, $payload, LOCK_EX) !== false;

        if ($written) {
            ActivityLogger::log('maintenance.enabled', 'system', null, 'Modo mantención activado');
            logger()->deploy('Modo mantención ACTIVADO');
        }

        return $written;
    }

    public function disable(): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $removed = @unlink($this->file);

        if ($removed) {
            ActivityLogger::log('maintenance.disabled', 'system', null, 'Modo mantención desactivado');
            logger()->deploy('Modo mantención DESACTIVADO');
        }

        return $removed;
    }

    /** Datos para la vista pública de mantención. */
    public function info(): array
    {
        $default = [
            'message'    => 'Estamos realizando una actualización. Volveremos en unos minutos.',
            'enabled_at' => null,
            'minutes'    => null,
        ];

        if (!$this->isEnabled()) {
            return $default;
        }

        $payload = json_decode((string) @file_get_contents($this->file), true);

        return is_array($payload) ? array_merge($default, $payload) : $default;
    }
}
