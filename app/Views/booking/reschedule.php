<?php
/**
 * Ruta: /app/Views/booking/reschedule.php
 */

use Core\View;

View::setMany([
    'step'      => 3,
    'stepName'  => 'Reprogramar',
    'backUrl'   => url('reserva/' . $booking['public_code'] . '?token=' . $token),
    'showSteps' => false,
]);
View::layout('booking');
View::start('content');
?>

<div class="booking-step-label">Reserva <?= e($booking['public_code']) ?></div>
<h1 class="booking-h1">Elige tu nueva hora</h1>

<div class="card card-muted mb-3">
    <div class="small muted">Actualmente</div>
    <strong><?= e(ucfirst(date_es($booking['booking_date'], true))) ?> · <?= e(time_hm($booking['start_time'])) ?> hrs</strong>
    <div class="small muted"><?= e($booking['service_name']) ?> con <?= e($booking['barber_name']) ?></div>
</div>

<form method="post" action="<?= e(url('reserva/' . $booking['public_code'] . '/reprogramar')) ?>" id="rescheduleForm" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="date" data-selected-date value="<?= e($selected) ?>">
    <input type="hidden" name="time" data-selected-time value="">

    <div class="date-strip" data-date-strip
         data-service="<?= (int) $booking['service_id'] ?>"
         data-barber="<?= (int) $booking['barber_id'] ?>">
        <?php foreach ($dates as $day): ?>
            <button type="button"
                    class="date-chip <?= $day['date'] === $selected ? 'is-selected' : '' ?> <?= $day['available'] ? '' : 'is-full' ?>"
                    data-date="<?= e($day['date']) ?>" <?= $day['available'] ? '' : 'disabled' ?>>
                <span class="dow"><?= e($day['weekday']) ?></span>
                <span class="num"><?= (int) $day['day'] ?></span>
                <span class="mon"><?= e($day['month']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <h2 style="font-size:1.05rem;margin:20px 0 12px">Horarios disponibles</h2>
    <div data-slots></div>
</form>

<?php
View::stop();
View::start('bar');
?>
<div class="booking-bar">
    <div class="container container-md booking-bar-inner">
        <div class="summary" data-summary>
            <strong>Elige un horario</strong>
            <span><?= e(date_es($selected)) ?></span>
        </div>
        <button type="submit" form="rescheduleForm" class="btn btn-primary is-disabled" data-continue disabled>
            Confirmar cambio
        </button>
    </div>
</div>
<?php View::stop(); ?>
