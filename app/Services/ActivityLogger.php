<?php
/**
 * Ruta: /app/Services/ActivityLogger.php
 * Registro de auditoría (spec §60). Nunca guarda datos sensibles en claro.
 */

namespace App\Services;

use App\Models\ActivityLog;
use Core\Auth;
use Core\Request;

final class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'token', '_token', 'github_token',
        'reset_token', 'secret', 'api_key',
    ];

    public static function log(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            $request = Request::current();

            (new ActivityLog())->create([
                'user_id'     => $userId ?? Auth::id(),
                'action'      => mb_substr($action, 0, 80),
                'entity_type' => $entityType,
                'entity_id'   => $entityId !== null ? (int) $entityId : null,
                'description' => $description !== null ? mb_substr($description, 0, 255) : null,
                'old_values'  => self::encode($oldValues),
                'new_values'  => self::encode($newValues),
                'ip_address'  => $request?->ip(),
                'user_agent'  => $request?->userAgent(),
                'created_at'  => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe romper la operación principal.
            logger()->warning('No se pudo registrar la actividad', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /** Registra sólo los campos que realmente cambiaron. */
    public static function logChanges(
        string $action,
        string $entityType,
        int $entityId,
        array $before,
        array $after,
        ?string $description = null
    ): void {
        $changedOld = [];
        $changedNew = [];

        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before)) {
                continue;
            }
            if ((string) $before[$key] !== (string) $value) {
                $changedOld[$key] = $before[$key];
                $changedNew[$key] = $value;
            }
        }

        if ($changedNew === []) {
            return;
        }

        self::log($action, $entityType, $entityId, $description, $changedOld, $changedNew);
    }

    private static function encode(?array $values): ?string
    {
        if ($values === null || $values === []) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $values[$key] = '***';
            }
        }

        return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /** Descripciones legibles para el panel. */
    public static function describe(string $action): string
    {
        return match ($action) {
            'booking.created'      => 'creó una reserva',
            'booking.updated'      => 'modificó una reserva',
            'booking.rescheduled'  => 'reprogramó una reserva',
            'booking.cancelled'    => 'canceló una reserva',
            'booking.status'       => 'cambió el estado de una reserva',
            'booking.no_show'      => 'marcó no asistió',
            'payment.registered'   => 'registró un pago',
            'payment.refunded'     => 'reembolsó un pago',
            'customer.created'     => 'creó un cliente',
            'customer.updated'     => 'actualizó un cliente',
            'barber.created'       => 'creó un barbero',
            'barber.updated'       => 'actualizó un barbero',
            'schedule.updated'     => 'actualizó un horario',
            'blocked.created'      => 'bloqueó un horario',
            'blocked.deleted'      => 'eliminó un bloqueo',
            'service.created'      => 'creó un servicio',
            'service.updated'      => 'actualizó un servicio',
            'user.created'         => 'creó un usuario',
            'user.updated'         => 'actualizó un usuario',
            'settings.updated'     => 'cambió la configuración',
            'auth.login'           => 'inició sesión',
            'auth.logout'          => 'cerró sesión',
            'auth.failed'          => 'intento de acceso fallido',
            'deploy.started'       => 'inició un despliegue',
            'deploy.success'       => 'completó un despliegue',
            'deploy.failed'        => 'falló un despliegue',
            'deploy.rollback'      => 'restauró una versión anterior',
            'github.connected'     => 'conectó el repositorio GitHub',
            'github.token_updated' => 'actualizó el token de GitHub',
            'maintenance.enabled'  => 'activó el modo mantención',
            'maintenance.disabled' => 'desactivó el modo mantención',
            default                => str_replace(['.', '_'], [' ', ' '], $action),
        };
    }
}
