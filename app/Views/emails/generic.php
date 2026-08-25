<?php
/**
 * Ruta: /app/Views/emails/generic.php
 * Plantilla usada por todos los tipos de notificación por email.
 * En la Etapa 2 se pueden crear plantillas dedicadas por tipo: EmailService
 * busca primero emails.{tipo} y sólo cae aquí si no existe.
 */

use App\Services\NotificationService;

$data = $data ?? [];
$type = $type ?? '';

[$heading, $intro, $closing] = match ($type) {
    NotificationService::BOOKING_CONFIRMED => [
        '¡Tu reserva está confirmada!',
        'Hola ' . ($data['first_name'] ?? '') . ', te esperamos en ' . ($data['business'] ?? 'Flava Studio') . '.',
        'Si necesitas cambiar o cancelar tu hora, puedes hacerlo desde el enlace de abajo.',
    ],
    NotificationService::BOOKING_RESCHEDULED => [
        'Tu reserva fue reprogramada',
        'Hola ' . ($data['first_name'] ?? '') . ', estos son los datos actualizados de tu hora.',
        'Nos vemos pronto.',
    ],
    NotificationService::BOOKING_CANCELLED => [
        'Tu reserva fue cancelada',
        'Hola ' . ($data['first_name'] ?? '') . ', tu reserva ' . ($data['code'] ?? '') . ' quedó cancelada.',
        'Cuando quieras puedes reservar una nueva hora en nuestro sitio.',
    ],
    NotificationService::BOOKING_REMINDER_1 => [
        'Te esperamos mañana',
        'Hola ' . ($data['first_name'] ?? '') . ', este es el recordatorio de tu hora.',
        'Te pedimos llegar 5 minutos antes.',
    ],
    NotificationService::BOOKING_REMINDER_2 => [
        'Tu hora es hoy',
        'Hola ' . ($data['first_name'] ?? '') . ', tu hora es hoy a las ' . ($data['time'] ?? '') . '.',
        'Te esperamos.',
    ],
    NotificationService::PAYMENT_RECEIVED => [
        'Comprobante de pago',
        'Hola ' . ($data['first_name'] ?? '') . ', recibimos tu pago. ¡Gracias!',
        'Guarda este correo como comprobante.',
    ],
    default => [
        'Información de tu reserva',
        'Hola ' . ($data['first_name'] ?? '') . ',',
        '',
    ],
};

ob_start();
?>
<h1 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#181818"><?= e($heading) ?></h1>
<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#6F6B63"><?= e($intro) ?></p>

<?php if (!empty($data['code'])): ?>
    <div style="background:#181818;border-radius:10px;padding:14px;text-align:center;margin-bottom:20px">
        <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,253,245,.55)">Código de reserva</div>
        <div style="font-family:monospace;font-size:19px;font-weight:700;color:#FFC400;letter-spacing:1px"><?= e($data['code']) ?></div>
    </div>
<?php endif; ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E7E5DE;border-radius:10px;overflow:hidden;margin-bottom:22px">
    <?php foreach ([
        'Servicio' => $data['service'] ?? '',
        'Barbero'  => $data['barber'] ?? '',
        'Fecha'    => ucfirst((string) ($data['date_long'] ?? '')),
        'Hora'     => ($data['time'] ?? '') . ' hrs',
        'Total'    => $data['total'] ?? '',
    ] as $label => $value): ?>
        <?php if ($value === '' || $value === ' hrs') continue; ?>
        <tr>
            <td style="padding:11px 16px;font-size:13px;color:#6F6B63;border-bottom:1px solid #F4F4F4"><?= e($label) ?></td>
            <td style="padding:11px 16px;font-size:14px;font-weight:600;text-align:right;border-bottom:1px solid #F4F4F4"><?= e($value) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<?php if (!empty($data['manage_url'])): ?>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 20px">
        <tr>
            <td style="background:#FFC400;border-radius:6px">
                <a href="<?= e($data['manage_url']) ?>"
                   style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;color:#181818;text-decoration:none">
                    Ver mi reserva
                </a>
            </td>
        </tr>
    </table>
<?php endif; ?>

<?php if ($closing !== ''): ?>
    <p style="margin:0;font-size:13px;line-height:1.6;color:#9A958C;text-align:center"><?= e($closing) ?></p>
<?php endif; ?>
<?php
$body    = (string) ob_get_clean();
$heading = $heading;

require \Core\View::path('emails.layout');
