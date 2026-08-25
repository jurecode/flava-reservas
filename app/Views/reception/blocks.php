<?php
/**
 * Ruta: /app/Views/reception/blocks.php
 */

use App\Models\BlockedTime;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Bloquear horario</h1>
        <p class="sub">Almuerzos, permisos y cierres puntuales.</p>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Nuevo bloqueo</h2>

        <form method="post" action="<?= e(url('recepcion/bloqueos')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="label" for="barber_id">Barbero</label>
                <select class="select" id="barber_id" name="barber_id">
                    <option value="">Todos (cierre del local)</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= (int) $barber['id'] ?>"><?= e($barber['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="type">Motivo</label>
                <select class="select" id="type" name="type" required>
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="start_date">Desde</label>
                    <input class="input" type="date" id="start_date" name="start_date" value="<?= e(today()) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="start_time">Hora</label>
                    <input class="input" type="time" id="start_time" name="start_time" value="13:00" step="300" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="end_date">Hasta</label>
                    <input class="input" type="date" id="end_date" name="end_date" value="<?= e(today()) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="end_time">Hora</label>
                    <input class="input" type="time" id="end_time" name="end_time" value="14:00" step="300" required>
                </div>
            </div>

            <div class="field">
                <label class="label" for="reason">Detalle</label>
                <input class="input" type="text" id="reason" name="reason" maxlength="255" placeholder="Opcional">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Bloquear</button>
            <p class="small muted center mt-2 mb-0">Si hay reservas en ese rango, te avisaremos antes de bloquear.</p>
        </form>
    </div>

    <div class="card card-flush">
        <div class="card-head"><h2>Bloqueos vigentes</h2></div>
        <div class="card-body stack-sm" style="max-height:520px;overflow-y:auto">
            <?php if ($blocks === []): ?>
                <?php $icon = 'check-circle'; $message = 'Sin bloqueos activos'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($blocks as $block): ?>
                    <div class="slot-row is-block">
                        <div class="slot-info">
                            <div class="who"><?= e(BlockedTime::typeLabel($block['type'])) ?> · <?= e($block['barber_name'] ?? 'Todo el local') ?></div>
                            <div class="what">
                                <?= e(substr((string) $block['start_datetime'], 0, 16)) ?> → <?= e(substr((string) $block['end_datetime'], 0, 16)) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::stop(); ?>
