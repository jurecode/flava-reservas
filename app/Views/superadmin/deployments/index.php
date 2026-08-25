<?php
/**
 * Ruta: /app/Views/superadmin/deployments/index.php
 * Panel de actualizaciones y rollback (spec §117, §129, §130).
 */

use App\Models\Deployment;
use App\Services\System\BackupService;
use Core\View;

View::layout('panel');
View::start('content');

$canDeploy = $status['github_enabled'] && $status['is_repository'] && $status['git_available'];
?>

<div class="page-head">
    <div>
        <h1>Despliegues</h1>
        <p class="sub">Actualiza flava.cl desde GitHub de manera controlada.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('super-admin/github')) ?>" class="btn btn-ghost btn-sm">Configurar GitHub</a>
    </div>
</div>

<?php if (!empty($result)): ?>
    <div class="card mb-3 <?= $result['ok'] ? 'card-outlined' : 'card-danger' ?>">
        <h2 style="font-size:1rem" class="row gap-sm">
            <?= $result['ok'] ? icon('check-circle', 17) : icon('x-circle', 17) ?>
            <?= $result['ok'] ? 'Despliegue completado' : 'El despliegue no se completó' ?>
        </h2>
        <p class="small"><?= e($result['message']) ?></p>

        <?php foreach ($result['steps'] as $step): ?>
            <div class="deploy-step <?= $step['ok'] ? 'ok' : 'fail' ?>">
                <span class="mark"><?= $step['ok'] ? icon('check', 15) : icon('close', 15) ?></span>
                <div class="grow">
                    <div><?= e($step['step']) ?></div>
                    <?php if (!empty($step['detail'])): ?>
                        <div class="detail"><?= e($step['detail']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid-2 gap-lg">
    <div class="stack">
        <!-- Ejecutar actualización -->
        <div class="card">
            <h2 style="font-size:1rem">Actualizar producción</h2>

            <?php if (!$canDeploy): ?>
                <div class="alert alert-warning mb-2">
                    <?= icon('zap', 17) ?>
                    <div>
                        <?php if (!$status['github_enabled']): ?>
                            La integración con GitHub está desactivada.
                        <?php elseif (!$status['git_available']): ?>
                            Este servidor no permite ejecutar Git desde PHP. Actualiza el código por SSH o cPanel
                            y luego ejecuta las <a href="<?= e(url('super-admin/migraciones')) ?>">migraciones</a> desde el panel.
                        <?php else: ?>
                            El directorio del servidor no es un repositorio Git.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="sys-row"><span class="k">Versión instalada</span><span class="v">v<?= e($status['version']) ?></span></div>
            <div class="sys-row"><span class="k">Commit</span><span class="v mono"><?= e($status['current_commit'] ?? '—') ?></span></div>
            <div class="sys-row"><span class="k">Rama</span><span class="v mono"><?= e($status['github_branch']) ?></span></div>
            <div class="sys-row">
                <span class="k">Respaldo previo</span>
                <span class="v"><?= setting('deploy_auto_backup', true) ? 'Sí' : 'No' ?></span>
            </div>
            <div class="sys-row">
                <span class="k">Mantención</span>
                <span class="v"><?= setting('deploy_maintenance', true) ? 'Sí' : 'No' ?></span>
            </div>

            <div class="alert alert-info mt-2">
                <?= icon('info', 17) ?>
                <div class="small">
                    <strong>El despliegue no toca:</strong> <code>.env</code>, <code>config/secrets.php</code>,
                    <code>config/database.php</code>, <code>/storage</code> ni <code>/public/uploads</code>.
                    La base de datos sólo cambia mediante migraciones.
                </div>
            </div>

            <form method="post" action="<?= e(url('super-admin/despliegues/ejecutar')) ?>" class="mt-2" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="confirm_password">Confirma tu contraseña</label>
                    <input class="input" type="password" id="confirm_password" name="confirm_password"
                           autocomplete="current-password" <?= $canDeploy ? '' : 'disabled' ?>>
                </div>

                <?php if ($status['is_repository'] && !$status['local_changes']['clean']): ?>
                    <label class="check mb-2">
                        <input type="checkbox" name="force" value="1">
                        <span class="small" style="color:var(--danger)">
                            Continuar aunque existan modificaciones locales en el servidor
                        </span>
                    </label>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary btn-lg btn-block" <?= $canDeploy ? '' : 'disabled' ?>>
                    <?= icon('rocket', 15) ?> Crear respaldo y actualizar
                </button>
            </form>
        </div>

        <?php if (!empty($updates) && $updates['available']): ?>
            <div class="card card-accent">
                <h2 style="font-size:1rem">Cambios pendientes</h2>
                <?php foreach ($updates['commits'] as $commit): ?>
                    <div class="commit-item">
                        <span class="commit-hash"><?= e($commit['short'] ?? '') ?></span>
                        <div class="small"><?= e($commit['message'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Respaldos -->
        <div class="card card-flush">
            <div class="card-head">
                <h2>Respaldos recientes</h2>
                <a href="<?= e(url('super-admin/respaldos')) ?>" class="btn btn-xs btn-ghost">Administrar</a>
            </div>
            <div class="card-body stack-sm">
                <?php if ($backups === []): ?>
                    <p class="small muted mb-0">Sin respaldos todavía.</p>
                <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                        <div class="row-between small" style="padding:7px 0;border-bottom:1px solid var(--line)">
                            <span class="mono tiny"><?= e($backup['name']) ?></span>
                            <span class="muted"><?= e(BackupService::humanSize($backup['size'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <!-- Historial de versiones -->
        <div class="card card-flush">
            <div class="card-head"><h2>Historial de versiones</h2></div>

            <div class="table-wrap">
                <table class="table" style="min-width:0">
                    <thead>
                        <tr><th>Cuándo</th><th>Commit</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if ($deployments === []): ?>
                            <tr><td colspan="4" class="center muted small" style="padding:26px">Sin despliegues registrados</td></tr>
                        <?php endif; ?>

                        <?php foreach ($deployments as $deployment): ?>
                            <tr>
                                <td class="small nowrap">
                                    <?= e(substr((string) $deployment['started_at'], 0, 16)) ?>
                                    <div class="tiny muted"><?= e(trim(($deployment['first_name'] ?? '') . ' ' . ($deployment['last_name'] ?? ''))) ?></div>
                                </td>
                                <td>
                                    <span class="commit-hash"><?= e(substr((string) $deployment['commit_hash'], 0, 7) ?: '—') ?></span>
                                    <?php if (!empty($deployment['migrations_run'])): ?>
                                        <div class="tiny muted"><?= (int) $deployment['migrations_run'] ?> migración(es)</div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= e(Deployment::statusBadge($deployment['status'])) ?>"><?= e(Deployment::statusLabel($deployment['status'])) ?></span></td>
                                <td class="right">
                                    <a href="<?= e(url('super-admin/despliegues/' . $deployment['id'])) ?>" class="btn btn-xs btn-light">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rollback -->
        <?php $lastSuccess = null; foreach ($deployments as $d) { if ($d['status'] === Deployment::SUCCESS && !empty($d['previous_commit'])) { $lastSuccess = $d; break; } } ?>

        <?php if ($lastSuccess !== null && $status['is_repository']): ?>
            <div class="danger-zone">
                <h3>Restaurar versión anterior</h3>
                <p class="small">
                    Volverá los <strong>archivos</strong> al commit
                    <span class="mono"><?= e(substr((string) $lastSuccess['previous_commit'], 0, 7)) ?></span>.
                </p>
                <p class="small">
                    <?= icon('alert', 15) ?> <strong>Los cambios de base de datos no se revierten automáticamente.</strong>
                    Si el despliegue incluyó migraciones, revísalas antes de continuar: los datos creados después
                    del despliegue se conservan.
                </p>

                <form method="post" action="<?= e(url('super-admin/despliegues/' . $lastSuccess['id'] . '/rollback')) ?>" data-once>
                    <?= csrf_field() ?>
                    <input class="input mb-2" type="password" name="confirm_password" placeholder="Tu contraseña" autocomplete="current-password">
                    <input class="input mb-2 mono" type="text" name="confirm" placeholder="Escribe RESTAURAR para confirmar" autocomplete="off">
                    <button type="submit" class="btn btn-danger btn-block">Restaurar versión</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Changelog -->
        <?php if ($changelog !== []): ?>
            <div class="card">
                <h2 style="font-size:1rem">CHANGELOG</h2>
                <?php foreach ($changelog as $entry): ?>
                    <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                        <strong><?= e($entry['heading']) ?></strong>
                        <div class="small muted" style="white-space:pre-line"><?= e($entry['body']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php View::stop(); ?>
