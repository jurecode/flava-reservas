<?php
/**
 * Ruta: /app/Services/NotificationService.php
 *
 * Encola notificaciones desde el día uno (spec §39). Los canales reales
 * (email/WhatsApp) se implementan en la Etapa 2: aquí sólo se registra la
 * intención en la tabla `notifications`, lista para que un cron la procese.
 */

namespace App\Services;

use App\Models\Notification;
use App\Services\Notifications\ChannelInterface;
use App\Support\DateHelper;
use App\Support\Money;

final class NotificationService
{
    // Tipos previstos (spec §39)
    public const BOOKING_CREATED     = 'booking_created';
    public const BOOKING_CONFIRMED   = 'booking_confirmed';
    public const BOOKING_REMINDER_1  = 'booking_reminder_24h';
    public const BOOKING_REMINDER_2  = 'booking_reminder_2h';
    public const BOOKING_RESCHEDULED = 'booking_rescheduled';
    public const BOOKING_CANCELLED   = 'booking_cancelled';
    public const PAYMENT_RECEIVED    = 'payment_received';

    public function __construct(
        private readonly Notification $notifications = new Notification(),
    ) {
    }

    // -----------------------------------------------------------------
    //  Eventos de negocio
    // -----------------------------------------------------------------

    public function bookingCreated(array $booking): void
    {
        $this->queue($booking, self::BOOKING_CONFIRMED, now()->format('Y-m-d H:i:s'));
        $this->scheduleReminders($booking);
    }

    public function bookingRescheduled(array $booking): void
    {
        $this->queue($booking, self::BOOKING_RESCHEDULED, now()->format('Y-m-d H:i:s'));
        $this->scheduleReminders($booking);
    }

    public function bookingCancelled(array $booking): void
    {
        $this->queue($booking, self::BOOKING_CANCELLED, now()->format('Y-m-d H:i:s'));
    }

    public function paymentReceived(array $booking, float $amount): void
    {
        $this->queue($booking, self::PAYMENT_RECEIVED, now()->format('Y-m-d H:i:s'), ['amount' => $amount]);
    }

    /** Programa los dos recordatorios configurables (spec §43). */
    public function scheduleReminders(array $booking): void
    {
        $start = DateHelper::make($booking['booking_date'] . ' ' . $booking['start_time']);

        foreach ([
            self::BOOKING_REMINDER_1 => (int) setting('booking_reminder_hours_1', 24),
            self::BOOKING_REMINDER_2 => (int) setting('booking_reminder_hours_2', 2),
        ] as $type => $hours) {
            if ($hours <= 0) {
                continue;
            }

            $scheduledAt = $start->modify("-{$hours} hours");

            // No programar recordatorios en el pasado.
            if ($scheduledAt <= DateHelper::make()) {
                continue;
            }

            $this->queue($booking, $type, $scheduledAt->format('Y-m-d H:i:s'));
        }
    }

    public function cancelReminders(int $bookingId): void
    {
        $this->notifications->cancelPendingForBooking($bookingId, [
            self::BOOKING_REMINDER_1,
            self::BOOKING_REMINDER_2,
        ]);
    }

    // -----------------------------------------------------------------
    //  Cola
    // -----------------------------------------------------------------

    /** Encola la notificación en todos los canales habilitados. */
    public function queue(array $booking, string $type, string $scheduledAt, array $extra = []): void
    {
        $payload = $this->buildPayload($booking, $extra);

        foreach ($this->enabledChannels() as $channel) {
            $recipient = match ($channel) {
                'email'    => $booking['customer_email'] ?? null,
                'whatsapp' => $booking['customer_whatsapp'] ?? $booking['customer_phone'] ?? null,
                default    => null,
            };

            if (!$recipient) {
                continue;
            }

            try {
                $this->notifications->create([
                    'customer_id'  => (int) $booking['customer_id'],
                    'booking_id'   => (int) $booking['id'],
                    'channel'      => $channel,
                    'type'         => $type,
                    'recipient'    => $recipient,
                    'subject'      => $this->subjectFor($type, $booking),
                    'payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'status'       => Notification::STATUS_PENDING,
                    'scheduled_at' => $scheduledAt,
                ]);
            } catch (\Throwable $e) {
                logger()->warning('No se pudo encolar la notificación', [
                    'type'    => $type,
                    'channel' => $channel,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Procesa la cola. Se invocará desde un cron en la Etapa 2:
     *   php /ruta/bin/flava notifications:process
     *
     * @return array{processed:int,sent:int,failed:int}
     */
    public function processQueue(int $limit = 50): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($this->notifications->due($limit) as $notification) {
            $stats['processed']++;

            try {
                $channel = $this->channel((string) $notification['channel']);

                if ($channel === null || !$channel->isEnabled()) {
                    // Canal no configurado: se deja pendiente sin gastar intentos.
                    continue;
                }

                $channel->send(
                    (string) $notification['recipient'],
                    (string) $notification['type'],
                    json_decode((string) $notification['payload'], true) ?? [],
                    (string) ($notification['subject'] ?? '')
                );

                $this->notifications->markSent((int) $notification['id']);
                $stats['sent']++;
            } catch (\Throwable $e) {
                $this->notifications->markFailed((int) $notification['id'], $e->getMessage());
                $stats['failed']++;
                logger()->error('Fallo al enviar notificación', [
                    'id'    => $notification['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /** @return array<int,string> */
    public function enabledChannels(): array
    {
        $channels = [];

        // Siempre se encola email: aunque el envío esté apagado, queda el registro.
        $channels[] = 'email';

        if ((bool) setting('whatsapp_enabled', false)) {
            $channels[] = 'whatsapp';
        }

        return $channels;
    }

    private function channel(string $name): ?ChannelInterface
    {
        return match ($name) {
            'email'    => new EmailService(),
            'whatsapp' => new WhatsAppService(),
            default    => null,
        };
    }

    /** Variables disponibles para las plantillas de mensaje. */
    private function buildPayload(array $booking, array $extra = []): array
    {
        return array_merge([
            'business'      => setting('business_name', config('app.name')),
            'code'          => $booking['public_code'] ?? '',
            'customer_name' => trim(($booking['customer_first_name'] ?? '') . ' ' . ($booking['customer_last_name'] ?? '')),
            'first_name'    => $booking['customer_first_name'] ?? '',
            'service'       => $booking['service_name'] ?? '',
            'barber'        => $booking['barber_name'] ?? '',
            'date'          => $booking['booking_date'] ?? '',
            'date_long'     => isset($booking['booking_date']) ? DateHelper::longEs((string) $booking['booking_date'], false, true) : '',
            'time'          => substr((string) ($booking['start_time'] ?? ''), 0, 5),
            'total'         => Money::format($booking['total'] ?? 0),
            'address'       => setting('business_address', ''),
            'manage_url'    => isset($booking['public_code'])
                ? url('reserva/' . $booking['public_code'])
                : url('/'),
        ], $extra);
    }

    private function subjectFor(string $type, array $booking): string
    {
        $business = setting('business_name', config('app.name'));
        $code     = $booking['public_code'] ?? '';

        return match ($type) {
            self::BOOKING_CONFIRMED   => "{$business} · Tu reserva {$code} está confirmada",
            self::BOOKING_RESCHEDULED => "{$business} · Tu reserva {$code} fue reprogramada",
            self::BOOKING_CANCELLED   => "{$business} · Tu reserva {$code} fue cancelada",
            self::BOOKING_REMINDER_1  => "{$business} · Te esperamos mañana",
            self::BOOKING_REMINDER_2  => "{$business} · Tu hora es en un rato",
            self::PAYMENT_RECEIVED    => "{$business} · Comprobante de pago {$code}",
            default                   => "{$business} · Información de tu reserva",
        };
    }
}
