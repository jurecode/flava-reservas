<?php
/**
 * Ruta: /app/Views/admin/schedules/blocks.php
 * Bloqueos de agenda (spec §22).
 */

use App\Models\BlockedTime;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Bloqueos de agenda</h1>
        <p class="sub">Almuerzos, permisos, vacaciones y cierres del local.</p>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Nuevo bloqueo</h2>

        <form method="post" action="<?= e(url('admin/bloqueos')) ?>" data-once>
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
                    <label class="label" for="start_time">Hora inicio</label>
                    <input class="input" type="time" id="start_time" name="start_time" value="13:00" step="300" required>
                </div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="end_date">Hasta</label>
                    <input class="input" type="date" id="end_date" name="end_date" value="<?= e(today()) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="end_time">Hora término</label>
                    <input class="input" type="time" id="end_time" name="end_time" value="14:00" step="300" required>
                </div>
            </div>

            <div class="field">
                <label class="label" for="reason">Detalle</label>
                <input class="input" type="text" id="reason" name="reason" maxlength="255" placeholder="Opcional">
            </div>

            <label class="check mb-2">
                <input type="checkbox" name="force" value="1">
                <span class="small">Bloquear aunque existan reservas (no las cancela: sólo avisa)</span>
            </label>

            <button type="submit" class="btn btn-primary btn-block">Crear bloqueo</button>
        </form>
    </div>

    <div class="card card-flush">
        <div class="card-head">
            <h2>Bloqueos vigentes</h2>
            <form method="get" class="row gap-sm">
                <input class="input" type="date" name="from" value="<?= e($from) ?>" style="padding:7px 10px;font-size:.84rem">
                <input class="input" type="date" name="to" value="<?= e($to) ?>" style="padding:7px 10px;font-size:.84rem">
                <button type="submit" class="btn btn-xs btn-dark">Ver</button>
            </form>
        </div>

        <div class="card-body stack-sm" style="max-height:560px;overflow-y:auto">
            <?php if ($blocks === []): ?>
                <?php $icon = 'check-circle'; $message = 'Sin bloqueos en este rango'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($blocks as $block): ?>
                    <div class="slot-row is-block">
                        <div class="slot-info">
                            <div class="who">
                                <?= e(BlockedTime::typeLabel($block['type'])) ?>
                                · <?= e($block['barber_name'] ?? 'Todo el local') ?>
                            </div>
                            <div class="what">
                                <?= e(substr((string) $block['start_datetime'], 0, 16)) ?>
                                → <?= e(substr((string) $block['end_datetime'], 0, 16)) ?>
                                <?php if (!empty($block['reason'])): ?> · <?= e($block['reason']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="slot-side">
                            <form method="post" action="<?= e(url('admin/bloqueos/' . $block['id'] . '/eliminar')) ?>"
                                  data-confirm="¿Eliminar este bloqueo? El horario volverá a estar disponible.">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-xs btn-ghost">Eliminar</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::stop(); ?>
