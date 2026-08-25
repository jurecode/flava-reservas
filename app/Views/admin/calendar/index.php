<?php
/**
 * Ruta: /app/Views/admin/calendar/index.php
 * Calendario administrativo (spec §33).
 * Los eventos llegan por AJAX desde /api/v1/admin/calendar/events.
 * Preparado para incorporar FullCalendar y drag & drop en una etapa posterior.
 */

use App\Support\BookingStatus;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Calendario</h1>
        <p class="sub">Vista de agenda por día y semana.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/reservas/nueva')) ?>" class="btn btn-primary btn-sm">+ Nueva reserva</a>
    </div>
</div>

<div class="card mb-2">
    <div class="row-between">
        <div class="row gap-sm">
            <button type="button" class="btn btn-light btn-sm" data-cal-prev aria-label="Anterior">‹</button>
            <button type="button" class="btn btn-light btn-sm" data-cal-today>Hoy</button>
            <button type="button" class="btn btn-light btn-sm" data-cal-next aria-label="Siguiente">›</button>
            <strong data-cal-title style="margin-left:8px"></strong>
        </div>

        <div class="row gap-sm">
            <select class="select" data-cal-barber style="padding:8px 32px 8px 12px;font-size:.86rem">
                <option value="">Todos los barberos</option>
                <?php foreach ($barbers as $barber): ?>
                    <option value="<?= (int) $barber['id'] ?>"><?= e($barber['display_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="row gap-sm">
                <button type="button" class="btn btn-sm btn-dark" data-cal-view="day">Día</button>
                <button type="button" class="btn btn-sm btn-ghost" data-cal-view="week">Semana</button>
            </div>
        </div>
    </div>
</div>

<div class="card card-flush">
    <div id="calendar" data-calendar data-date="<?= e($date) ?>" style="min-height:420px">
        <div class="center" style="padding:60px"><span class="spinner spinner-lg"></span></div>
    </div>
</div>

<div class="card mt-2">
    <div class="row gap-lg">
        <?php foreach (BookingStatus::all() as $status): ?>
            <span class="row gap-sm small">
                <span style="width:12px;height:12px;border-radius:3px;background:<?= e(BookingStatus::color($status)) ?>"></span>
                <?= e(BookingStatus::label($status)) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<?php View::stop(); ?>
