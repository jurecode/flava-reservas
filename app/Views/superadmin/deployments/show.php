<?php
/**
 * Ruta: /app/Views/superadmin/deployments/show.php
 */

use App\Models\Deployment;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Despliegue #<?= (int) $deployment['id'] ?></h1>
        <p class="sub"><?= e($deployment['started_at']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('super-admin/despliegues')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="card" style="max-width:660px">
    <div class="sys-row"><span class="k">Estado</span><span class="v"><span class="badge <?= e(Deployment::statusBadge($deployment['status'])) ?>"><?= e(Deployment::statusLabel($deployment['status'])) ?></span></span></div>
    <div class="sys-row"><span class="k">Versión</span><span class="v">v<?= e($deployment['version'] ?? '—') ?></span></div>
    <div class="sys-row"><span class="k">Rama</span><span class="v mono"><?= e($deployment['branch'] ?? '—') ?></span></div>
    <div class="sys-row"><span class="k">Commit anterior</span><span class="v mono"><?= e(substr((string) $deployment['previous_commit'], 0, 12) ?: '—') ?></span></div>
    <div class="sys-row"><span class="k">Commit aplicado</span><span class="v mono"><?= e(substr((string) $deployment['commit_hash'], 0, 12) ?: '—') ?></span></div>
    <div class="sys-row"><span class="k">Estrategia</span><span class="v"><?= e(strtoupper((string) $deployment['strategy'])) ?></span></div>
    <div class="sys-row"><span class="k">Migraciones</span><span class="v"><?= (int) $deployment['migrations_run'] ?></span></div>
    <div class="sys-row"><span class="k">Respaldo</span><span class="v mono small"><?= e($deployment['backup_path'] ?? '—') ?></span></div>
    <div class="sys-row"><span class="k">Inicio</span><span class="v small"><?= e($deployment['started_at']) ?></span></div>
    <div class="sys-row"><span class="k">Término</span><span class="v small"><?= e($deployment['finished_at'] ?? '—') ?></span></div>

    <?php if (!empty($deployment['notes'])): ?>
        <div class="mt-2">
            <div class="label">Notas</div>
            <p class="small mb-0"><?= nl2br(e($deployment['notes'])) ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($deployment['error_message'])): ?>
        <div class="alert alert-error mt-2 mb-0">
            <?= icon('alert', 17) ?>
            <div class="small mono"><?= nl2br(e($deployment['error_message'])) ?></div>
        </div>
    <?php endif; ?>
</div>

<p class="small muted mt-2">
    El detalle técnico completo queda en <code>/storage/logs/deploy.log</code>
    (<a href="<?= e(url('super-admin/logs?file=deploy.log')) ?>">ver log</a>), siempre sin credenciales.
</p>

<?php View::stop(); ?>
