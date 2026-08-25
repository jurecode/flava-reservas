<?php
/**
 * Ruta: /app/Views/booking/date.php
 * PASOS 3 y 4 — Fecha y hora en una sola pantalla.
 */

use Core\View;

View::setMany([
    'step'     => 3,
    'stepName' => 'Fecha y hora',
    'backUrl'  => url('reservar/barbero?service_id=' . (int) $service['id']),
]);
$backUrl = url('reservar/barbero?service_id=' . (int) $service['id']);
View::layout('booking');
View::start('content');
?>

<div class="booking-step-label">Paso 3</div>
<h1 class="booking-h1">¿Cuándo te acomoda?</h1>

<div class="chosen">
    <span class="ico-box ico-box-accent"><?= icon('scissors', 17) ?></span>
    <div class="grow">
        <strong><?= e($service['name']) ?></strong>
        <span>
            <?= (int) $service['duration_minutes'] ?> min ·
            <?= $barber !== null ? e($barber['display_name']) : 'Cualquier barbero disponible' ?>
        </span>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-xs btn-ghost">Cambiar</a>
</div>

<form method="get" action="<?= e(url('reservar/checkout')) ?>" id="dateForm">
    <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
    <input type="hidden" name="barber_id" value="<?= e($barber_id) ?>" data-resolved-barber>
    <input type="hidden" name="date" data-selected-date value="<?= e($selected) ?>">
    <input type="hidden" name="time" data-selected-time value="">

    <div class="date-strip" data-date-strip
         data-service="<?= (int) $service['id'] ?>"
         data-barber="<?= e($barber_id) ?>">
        <?php foreach ($dates as $day): ?>
            <button type="button"
                    class="date-chip <?= $day['date'] === $selected ? 'is-selected' : '' ?> <?= $day['available'] ? '' : 'is-full' ?>"
                    data-date="<?= e($day['date']) ?>"
                    <?= $day['available'] ? '' : 'disabled' ?>>
                <span class="dow"><?= e($day['weekday']) ?></span>
                <span class="num"><?= (int) $day['day'] ?></span>
                <span class="mon"><?= e($day['month']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <h2 style="font-size:1.05rem;margin:20px 0 12px">Horarios disponibles</h2>

    <div data-slots data-prerendered="1">
        <?php if ($slots === []): ?>
            <div class="empty">
                <span class="ico-box ico-box-lg"><?= icon('calendar', 22) ?></span>
                <p class="bold mb-1">No quedan horarios ese día</p>
                <p class="small muted">Prueba con otra fecha del selector.</p>
            </div>
        <?php else: ?>
            <div class="slot-grid">
                <?php foreach ($slots as $slot): ?>
                    <button type="button" class="slot" data-time="<?= e($slot['time']) ?>"
                            <?php if (isset($slot['barber_id'])): ?>data-barber="<?= (int) $slot['barber_id'] ?>"<?php endif; ?>>
                        <?= e($slot['time']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
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
        <button type="submit" form="dateForm" class="btn btn-primary is-disabled" data-continue disabled>
            Continuar
        </button>
    </div>
</div>
<?php View::stop(); ?>

<?php View::start('scripts'); ?>
<script>
// Los horarios de la fecha inicial ya vienen renderizados desde el servidor:
// el JS toma el control desde el primer cambio de fecha.
document.addEventListener('DOMContentLoaded', () => {
    const zone = document.querySelector('[data-slots]');
    const timeInput = document.querySelector('[data-selected-time]');
    const submit = document.querySelector('[data-continue]');
    const summary = document.querySelector('[data-summary]');
    const resolved = document.querySelector('[data-resolved-barber]');

    zone?.querySelectorAll('.slot').forEach((button) => {
        button.addEventListener('click', () => {
            zone.querySelectorAll('.slot').forEach((s) => s.classList.remove('is-selected'));
            button.classList.add('is-selected');
            timeInput.value = button.dataset.time;
            if (resolved && button.dataset.barber) resolved.value = button.dataset.barber;
            submit.disabled = false;
            submit.classList.remove('is-disabled');
            summary.querySelector('strong').textContent = button.dataset.time + ' hrs';
        });
    });

    // A partir de aquí, los cambios de fecha los maneja booking.js
    document.querySelector('[data-date-strip]')?.addEventListener('click', () => {
        zone?.removeAttribute('data-prerendered');
    }, { once: true });
});
</script>
<?php View::stop(); ?>
