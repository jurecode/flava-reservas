<?php
/**
 * Ruta: /app/Views/barber/blocks.php
 * El barbero bloquea su propia agenda (almuerzo, trámite, permiso).
 */

use App\Models\BlockedTime;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Mis bloqueos</h1>
        <p class="sub">Marca las horas en que no estarás disponible.</p>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Nuevo bloqueo</h2>

        <form method="post" action="<?= e(url('barbero/bloqueos')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="label" for="type">Motivo</label>
                <select class="select" id="type" name="type" required>
                    <?php foreach ($types as $value => $label): ?>
                        <?php if ($value === 'holiday') continue; ?>
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
            <p class="small muted center mt-2 mb-0">Si tienes reservas en ese rango, avisa a recepción para reprogramarlas.</p>
        </form>
    </div>

    <div class="card card-flush">
        <div class="card-head"><h2>Bloqueos próximos</h2></div>
        <div class="card-body stack-sm" style="max-height:520px;overflow-y:auto">
            <?php if ($blocks === []): ?>
                <?php $icon = 'check-circle'; $message = 'Sin bloqueos programados'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($blocks as $block): ?>
                    <div class="slot-row is-block">
                        <div class="slot-info">
                            <div class="who"><?= e(BlockedTime::typeLabel($block['type'])) ?></div>
                            <div class="what">
                                <?= e(substr((string) $block['start_datetime'], 0, 16)) ?> → <?= e(substr((string) $block['end_datetime'], 0, 16)) ?>
                                <?php if (!empty($block['reason'])): ?> · <?= e($block['reason']) ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="slot-side">
                            <?php if ($block['barber_id'] !== null): ?>
                                <form method="post" action="<?= e(url('barbero/bloqueos/' . $block['id'] . '/eliminar')) ?>"
                                      data-confirm="¿Eliminar este bloqueo?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-xs btn-ghost">Eliminar</button>
                                </form>
                            <?php else: ?>
                                <span class="tiny muted">Cierre del local</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::stop(); ?>
