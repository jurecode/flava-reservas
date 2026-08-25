<?php
/**
 * Ruta: /app/Services/WhatsAppService.php
 *
 * Preparado para conectar WhatsApp Business Cloud API (u otro proveedor
 * autorizado) en la Etapa 2. Mientras tanto compone el mensaje y lo registra.
 */

namespace App\Services;

use App\Services\Notifications\ChannelInterface;
use App\Support\Str;

final class WhatsAppService implements ChannelInterface
{
    public function isEnabled(): bool
    {
        return (bool) setting('whatsapp_enabled', false) && $this->driver() !== 'off';
    }

    public function driver(): string
    {
        return (string) env('WHATSAPP_DRIVER', 'log');
    }

    public function send(string $recipient, string $type, array $payload, string $subject = ''): bool
    {
        $phone = Str::phone($recipient);

        if ($phone === null) {
            throw new \RuntimeException('Número de WhatsApp inválido');
        }

        $message = $this->compose($type, $payload);

        return match ($this->driver()) {
            'log'   => $this->logOnly($phone, $message),
            'cloud' => throw new \RuntimeException('Driver WhatsApp Cloud API pendiente (Etapa 2)'),
            default => throw new \RuntimeException('Driver de WhatsApp no implementado: ' . $this->driver()),
        };
    }

    /** Mensajes con el tono de Flava Studio (spec §41). */
    public function compose(string $type, array $data): string
    {
        $name     = $data['first_name'] ?? '';
        $business = $data['business'] ?? config('app.name');

        $detail = "✂️ {$data['service']}\n"
            . "👤 {$data['barber']}\n"
            . "📅 " . ucfirst((string) ($data['date_long'] ?? '')) . "\n"
            . "🕒 {$data['time']} hrs\n\n"
            . "Código:\n{$data['code']}";

        return match ($type) {
            NotificationService::BOOKING_CONFIRMED =>
                "Hola {$name} 👋\n\nTu reserva en {$business} está confirmada.\n\n{$detail}\n\nTe esperamos.",

            NotificationService::BOOKING_RESCHEDULED =>
                "Hola {$name} 👋\n\nTu reserva en {$business} quedó reprogramada.\n\n{$detail}\n\nNos vemos.",

            NotificationService::BOOKING_CANCELLED =>
                "Hola {$name}.\n\nTu reserva {$data['code']} en {$business} fue cancelada.\n\n"
                . "Puedes reservar una nueva hora cuando quieras en " . config('app.url'),

            NotificationService::BOOKING_REMINDER_1 =>
                "Hola {$name} 👋\n\nTe recordamos tu hora de mañana en {$business}.\n\n{$detail}",

            NotificationService::BOOKING_REMINDER_2 =>
                "Hola {$name} 👋\n\nTu hora en {$business} es hoy a las {$data['time']}.\n\n{$detail}\n\n"
                . ($data['address'] ?? ''),

            NotificationService::PAYMENT_RECEIVED =>
                "Hola {$name} 👋\n\nRecibimos tu pago de {$data['total']} en {$business}.\n\nCódigo: {$data['code']}\n\n¡Gracias!",

            default => "Hola {$name} 👋\n\n{$business}\n\n{$detail}",
        };
    }

    private function logOnly(string $phone, string $message): bool
    {
        logger()->info('[WHATSAPP simulado]', [
            'to'      => $phone,
            'message' => mb_substr($message, 0, 200),
        ]);

        return true;
    }

    /** Enlace wa.me para que el personal escriba manualmente desde el panel. */
    public function manualLink(?string $phone, string $message = ''): ?string
    {
        return Str::whatsappLink($phone, $message);
    }
}
