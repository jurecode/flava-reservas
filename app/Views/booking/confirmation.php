<?php
/**
 * Ruta: /app/Views/booking/confirmation.php
 * Página de confirmación y gestión de la reserva (spec §28).
 */

use App\Support\BookingStatus;
use App\Support\PaymentStatus;
use App\Support\Str;
use Core\View;

View::setMany(['step' => 4, 'stepName' => 'Confirmada', 'backUrl' => url('/'), 'showSteps' => false]);
$manage = url('reserva/' . $booking['public_code']) . '?token=' . $token;
$isCancelled = $booking['status'] === BookingStatus::CANCELLED;

View::layout('booking');
View::start('content');
?>

<div class="confirm-hero">
    <?php if ($isCancelled): ?>
        <div class="confirm-check is-cancelled"><?= icon('close', 28) ?></div>
        <h1 style="font-size:1.6rem">Reserva cancelada</h1>
        <p class="muted">Esta reserva ya no está activa. Puedes reservar una nueva hora cuando quieras.</p>
    <?php else: ?>
        <div class="confirm-check"><?= icon('check', 30) ?></div>
        <h1 style="font-size:1.6rem">¡Tu reserva está confirmada!</h1>
        <p class="muted">Te esperamos. Guarda tu código.</p>
    <?php endif; ?>

    <div class="confirm-code">
        <span><?= e($booking['public_code']) ?></span>
        <button type="button" class="confirm-copy" data-copy="<?= e($booking['public_code']) ?>" aria-label="Copiar código">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 012-2h10"/>
            </svg>
        </button>
    </div>
</div>

<div class="summary-card">
    <h3>Detalle</h3>
    <div class="summary-row"><span class="k">Servicio</span><span class="v"><?= e($booking['service_name']) ?></span></div>
    <div class="summary-row"><span class="k">Barbero</span><span class="v"><?= e($booking['barber_name']) ?></span></div>
    <div class="summary-row"><span class="k">Fecha</span><span class="v"><?= e(ucfirst(date_es($booking['booking_date'], true))) ?></span></div>
    <div class="summary-row"><span class="k">Hora</span><span class="v"><?= e(time_hm($booking['start_time'])) ?> hrs</span></div>
    <div class="summary-row"><span class="k">Duración</span><span class="v"><?= (int) $booking['duration_minutes'] ?> min</span></div>
    <div class="summary-row">
        <span class="k">Estado</span>
        <span class="v"><?= e(BookingStatus::label($booking['status'])) ?></span>
    </div>
    <?php if ($booking['payment_status'] === PaymentStatus::PAID): ?>
        <div class="summary-row"><span class="k">Pago</span><span class="v">Pagado</span></div>
    <?php endif; ?>

    <div class="summary-total">
        <span class="k">Total</span>
        <span class="v"><?= e(money($booking['total'])) ?></span>
    </div>
</div>

<?php if (!$isCancelled): ?>
    <div class="stack mt-3">
        <a href="<?= e(url('reserva/' . $booking['public_code'] . '/calendario?token=' . $token)) ?>" class="btn btn-dark btn-block">
            <?= icon('calendar', 15) ?> Agregar al calendario
        </a>

        <?php if (!empty($business['address'])): ?>
            <div class="card">
                <div class="row row-nowrap gap-sm">
                    <span class="ico-box"><?= icon('map-pin', 16) ?></span>
                    <div class="grow">
                        <strong class="small">Dónde llegar</strong>
                        <div class="small muted"><?= e($business['address']) ?></div>
                    </div>
                    <?php if (!empty($business['maps_url'])): ?>
                        <a href="<?= e($business['maps_url']) ?>" target="_blank" rel="noopener" class="btn btn-xs btn-ghost">Mapa</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row gap-sm">
            <?php if ($can_move): ?>
                <a href="<?= e(url('reserva/' . $booking['public_code'] . '/reprogramar?token=' . $token)) ?>" class="btn btn-ghost grow">Reprogramar</a>
            <?php endif; ?>
            <?php if ($can_cancel): ?>
                <a href="<?= e(url('reserva/' . $booking['public_code'] . '/cancelar?token=' . $token)) ?>" class="btn btn-ghost grow">Cancelar</a>
            <?php endif; ?>
        </div>

        <?php if (!$can_move && !$can_cancel && BookingStatus::isCancellable((string) $booking['status'])): ?>
            <p class="small muted center">
                Para cambios de última hora escríbenos por WhatsApp
                <?php if ($whatsapp = Str::whatsappLink($business['whatsapp'] ?? null, 'Hola, necesito modificar mi reserva ' . $booking['public_code'])): ?>
                    · <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="bold">Escribir</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div class="card card-muted">
            <p class="small muted mb-1"><strong>Guarda este enlace privado</strong> para volver a ver o modificar tu reserva:</p>
            <div class="row row-nowrap gap-sm">
                <input class="input small mono" readonly value="<?= e($manage) ?>" onclick="this.select()" style="padding:9px 11px">
                <button type="button" class="btn btn-sm btn-light" data-copy="<?= e($manage) ?>">Copiar</button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="center mt-3">
        <a href="<?= e(url('reservar')) ?>" class="btn btn-primary btn-lg">Reservar una nueva hora</a>
    </div>
<?php endif; ?>

<div class="center mt-4">
    <a href="<?= e(url('/')) ?>" class="small muted">Volver al inicio</a>
</div>

<?php View::stop(); ?>
