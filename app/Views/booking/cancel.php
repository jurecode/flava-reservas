<?php
/**
 * Ruta: /app/Views/booking/cancel.php
 */

use Core\View;

$backUrl = url('reserva/' . $booking['public_code'] . '?token=' . $token);
View::setMany(['step' => 4, 'stepName' => 'Cancelar', 'backUrl' => $backUrl, 'showSteps' => false]);
View::layout('booking');
View::start('content');
?>

<h1 class="booking-h1">¿Cancelar tu reserva?</h1>

<div class="card mb-3">
    <div class="small muted">Reserva <?= e($booking['public_code']) ?></div>
    <strong><?= e($booking['service_name']) ?></strong>
    <div class="small muted">
        <?= e(ucfirst(date_es($booking['booking_date'], true))) ?> · <?= e(time_hm($booking['start_time'])) ?> hrs
        con <?= e($booking['barber_name']) ?>
    </div>
</div>

<?php if (!$allowed): ?>
    <div class="alert alert-warning">
        <?= icon('zap', 17) ?>
        <div><?= e($message) ?></div>
    </div>

    <a href="<?= e($backUrl) ?>" class="btn btn-ghost btn-block">Volver a mi reserva</a>
<?php else: ?>
    <form method="post" action="<?= e(url('reserva/' . $booking['public_code'] . '/cancelar')) ?>" data-once>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label class="label" for="reason">Motivo <span class="muted">(opcional)</span></label>
            <textarea class="textarea" id="reason" name="reason" rows="3" maxlength="255"
                      placeholder="Nos ayuda a mejorar"></textarea>
        </div>

        <button type="submit" class="btn btn-danger btn-block btn-lg">Sí, cancelar mi reserva</button>
        <a href="<?= e($backUrl) ?>" class="btn btn-ghost btn-block mt-2">No, mantenerla</a>
    </form>
<?php endif; ?>

<?php View::stop(); ?>
