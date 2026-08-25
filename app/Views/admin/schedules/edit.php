<?php
/**
 * Ruta: /app/Views/admin/schedules/edit.php
 * Horario semanal con varios bloques por día (spec §21).
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Horario de <?= e($barber['display_name']) ?></h1>
        <p class="sub">Puedes definir más de un bloque por día (por ejemplo, mañana y tarde).</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/barberos')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<form method="post" action="<?= e(url('admin/barberos/' . $barber['id'] . '/horario')) ?>" data-once>
    <?= csrf_field() ?>

    <div class="week-editor" data-week-editor>
        <?php foreach ($days as $weekday => $dayName): ?>
            <?php $blocks = $week[$weekday] ?? []; ?>
            <div class="day-row <?= $blocks === [] ? 'is-off' : '' ?>" data-day="<?= (int) $weekday ?>">
                <div class="day-head">
                    <span class="day-name"><?= e($dayName) ?></span>
                    <?php if ($blocks === []): ?>
                        <span class="badge badge-muted">Libre</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-xs btn-light" style="margin-left:auto" data-add-block="<?= (int) $weekday ?>">
                        + Agregar bloque
                    </button>
                </div>

                <div class="day-blocks" data-blocks="<?= (int) $weekday ?>">
                    <?php foreach ($blocks as $index => $block): ?>
                        <div class="time-block">
                            <input class="input" type="time" name="schedule[<?= (int) $weekday ?>][<?= $index ?>][start_time]"
                                   value="<?= e(time_hm($block['start_time'])) ?>" step="300">
                            <span class="muted">a</span>
                            <input class="input" type="time" name="schedule[<?= (int) $weekday ?>][<?= $index ?>][end_time]"
                                   value="<?= e(time_hm($block['end_time'])) ?>" step="300">
                            <button type="button" class="btn btn-xs btn-ghost" data-remove-block>Quitar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row-between mt-3">
        <div class="row gap-sm">
            <button type="button" class="btn btn-ghost btn-sm" data-preset="weekdays">Lun–Vie 10:00–20:00</button>
            <button type="button" class="btn btn-ghost btn-sm" data-preset="fullweek">Lun–Sáb 10:00–20:00</button>
        </div>
        <button type="submit" class="btn btn-primary">Guardar horario</button>
    </div>
</form>

<div class="card card-muted mt-3">
    <p class="small muted mb-0">
        <?= icon('info', 14) ?> Los horarios definen la <strong>disponibilidad base</strong>. Los almuerzos, permisos y vacaciones se
        gestionan en <a href="<?= e(url('admin/bloqueos')) ?>">Bloqueos</a>, para no tener que editar el horario cada vez.
    </p>
</div>

<?php View::stop(); ?>
