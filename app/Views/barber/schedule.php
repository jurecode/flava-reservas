<?php
/**
 * Ruta: /app/Views/barber/schedule.php
 * El barbero consulta su horario; sólo administración puede modificarlo.
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Mi horario</h1>
        <p class="sub">Jornada semanal definida por administración.</p>
    </div>
</div>

<div class="card" style="max-width:560px">
    <?php foreach ($days as $weekday => $dayName): ?>
        <?php $blocks = $week[$weekday] ?? []; ?>
        <div class="row-between" style="padding:12px 0;border-bottom:1px solid var(--line)">
            <span class="bold" style="text-transform:capitalize"><?= e($dayName) ?></span>
            <span>
                <?php if ($blocks === []): ?>
                    <span class="badge badge-muted">Libre</span>
                <?php else: ?>
                    <?php foreach ($blocks as $block): ?>
                        <span class="badge badge-confirmed"><?= e(time_hm($block['start_time'])) ?> – <?= e(time_hm($block['end_time'])) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>

    <p class="small muted mt-3 mb-0">
        ¿Necesitas cambiar tu jornada? Habla con administración.
        Para ausencias puntuales usa <a href="<?= e(url('barbero/bloqueos')) ?>">Mis bloqueos</a>.
    </p>
</div>

<?php View::stop(); ?>
