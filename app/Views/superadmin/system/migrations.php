<?php
/**
 * Ruta: /app/Views/superadmin/system/migrations.php
 * Migraciones de base de datos (spec §123).
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Migraciones</h1>
        <p class="sub">Cambios de estructura de la base de datos. Cada archivo se ejecuta una sola vez.</p>
    </div>
</div>

<div class="alert alert-info">
    <?= icon('info', 17) ?>
    <div class="small">
        <code>/database/flava.sql</code> es <strong>sólo para instalaciones nuevas</strong>.
        En producción la base evoluciona exclusivamente mediante los archivos de <code>/database/migrations</code>.
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Pendientes</h2>

        <?php if ($pending === []): ?>
            <p class="small muted"><?= icon('check-circle', 14) ?> No hay migraciones pendientes. La base está al día.</p>
        <?php else: ?>
            <div class="stack-sm mb-2">
                <?php foreach ($pending as $file): ?>
                    <div class="row row-nowrap gap-sm small" style="padding:8px 0;border-bottom:1px solid var(--line)">
                        <span class="badge badge-pending">Pendiente</span>
                        <span class="mono tiny grow truncate"><?= e($file) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-warning">
                <?= icon('zap', 17) ?>
                <div class="small">Se creará un <strong>respaldo automático</strong> antes de ejecutarlas. Si una falla, se revierte y el proceso se detiene.</div>
            </div>

            <form method="post" action="<?= e(url('super-admin/migraciones/ejecutar')) ?>"
                  data-confirm="Se creará un respaldo y luego se ejecutarán <?= count($pending) ?> migración(es). ¿Continuar?" data-once>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-block">Respaldar y ejecutar (<?= count($pending) ?>)</button>
            </form>
        <?php endif; ?>

        <hr class="divider">

        <p class="tiny muted mb-0">
            Para crear una migración nueva en tu entorno local:<br>
            <code>php bin/flava make:migration "agregar columna x"</code>
        </p>
    </div>

    <div class="card card-flush">
        <div class="card-head">
            <h2>Ejecutadas</h2>
            <span class="small muted"><?= count($history) ?> registro(s)</span>
        </div>

        <div class="table-wrap">
            <table class="table" style="min-width:0">
                <thead>
                    <tr><th>Migración</th><th class="right">Lote</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php if ($history === []): ?>
                        <tr><td colspan="3" class="center muted small" style="padding:24px">Sin migraciones registradas</td></tr>
                    <?php endif; ?>

                    <?php foreach ($history as $migration): ?>
                        <tr>
                            <td class="mono tiny"><?= e($migration['migration']) ?></td>
                            <td class="right small"><?= (int) $migration['batch'] ?></td>
                            <td class="small muted nowrap"><?= e(substr((string) $migration['executed_at'], 0, 16)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php View::stop(); ?>
