<?php
/**
 * Ruta: /app/Services/Notifications/ChannelInterface.php
 * Contrato común de los canales de notificación. Permite agregar proveedores
 * (SMTP, WhatsApp Cloud API, SMS) sin tocar NotificationService.
 */

namespace App\Services\Notifications;

interface ChannelInterface
{
    /** ¿El canal está configurado y habilitado? */
    public function isEnabled(): bool;

    /**
     * @param array<string,mixed> $payload variables de la plantilla
     * @throws \RuntimeException si el envío falla
     */
    public function send(string $recipient, string $type, array $payload, string $subject = ''): bool;
}
