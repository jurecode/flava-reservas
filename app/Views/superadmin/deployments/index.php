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
        <!-- Estado de las capacidades del servidor -->
        <div class="card">
            <h2 style="font-size:1rem">Actualizar producción</h2>

            <?php
                // Cada requisito por separado: así el panel dice exactamente
                // qué falta, en vez de deshabilitar el botón sin explicación.
                $requisitos = [
                    [
                        'ok'    => $status['github_enabled'],
                        'label' => 'Integración con GitHub activa',
                        'falta' => 'Actívala en Configurar GitHub, marcando «Integración activa».',
                    ],
                    [
                        'ok'    => $status['github_token'],
                        'label' => 'Token de acceso configurado',
                        'falta' => 'Guarda un token en Configurar GitHub.',
                    ],
                    [
                        'ok'    => $status['git_available'],
                        'label' => 'PHP puede ejecutar Git',
                        'falta' => 'Tu hosting no lo permite. La actualización de archivos se hace desde el panel del hosting.',
                    ],
                    [
                        'ok'    => $status['is_repository'],
                        'label' => 'La carpeta es un repositorio Git',
                        'falta' => 'Los archivos se subieron a mano. Para desplegar desde aquí habría que clonar el repositorio en el servidor.',
                    ],
                ];
            ?>

            <?php foreach ($requisitos as $requisito): ?>
                <div class="check-row">
                    <span class="<?= $requisito['ok'] ? 'ok' : 'muted' ?>">
                        <?= $requisito['ok'] ? icon('check-circle', 16) : icon('x-circle', 16) ?>
                    </span>
                    <div class="grow">
                        <div class="small bold"><?= e($requisito['label']) ?></div>
                        <?php if (!$requisito['ok']): ?>
                            <div class="tiny muted"><?= e($requisito['falta']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <hr class="divider">

            <div class="data-row"><span class="k">Versión instalada</span><span class="v">v<?= e($status['version']) ?></span></div>
            <div class="data-row"><span class="k">Commit</span><span class="v mono"><?= e($status['current_commit'] ?? '—') ?></span></div>
            <div class="data-row"><span class="k">Rama</span><span class="v mono"><?= e($status['github_branch']) ?></span></div>

            <?php if ($canDeploy): ?>
                <div class="alert alert-info mt-2">
                    <?= icon('info', 16) ?>
                    <div class="small">
                        <strong>El despliegue no toca:</strong> <code>.env</code>,
                        <code>config/secrets.php</code>, <code>config/database.php</code>,
                        <code>/storage</code> ni <code>/public/uploads</code>.
                        La base de datos sólo cambia mediante migraciones.
                    </div>
                </div>

                <form method="post" action="<?= e(url('super-admin/despliegues/ejecutar')) ?>" class="mt-2" data-once>
                    <?= csrf_field() ?>

                    <div class="field">
                        <label class="label" for="confirm_password">Confirma tu contraseña</label>
                        <input class="input" type="password" id="confirm_password" name="confirm_password"
                               autocomplete="current-password">
                    </div>

                    <?php if ($status['is_repository'] && !$status['local_changes']['clean']): ?>
                        <label class="check mb-2">
                            <input type="checkbox" name="force" value="1">
                            <span class="small" style="color:var(--danger)">
                                Continuar aunque existan modificaciones locales en el servidor
                            </span>
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <?= icon('rocket', 16) ?> Crear respaldo y actualizar
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mt-2 mb-0">
                    <?= icon('zap', 16) ?>
                    <div class="small">
                        El despliegue automático no está disponible con la configuración actual.
                        Abajo tienes el procedimiento que sí funciona en tu servidor.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Camino alternativo: siempre visible cuando no se puede automatizar -->
        <?php if (!$canDeploy): ?>
            <div class="card">
                <h2 style="font-size:1rem"><?= icon('list', 16) ?> Cómo actualizar tu servidor</h2>
                <p class="small muted" style="margin-top:-4px">
                    En hosting compartido los archivos se actualizan desde el panel del
                    hosting; la base de datos, desde aquí.
                </p>

                <div class="next-steps mt-2">
                    <div class="next-step">
                        <span class="num">1</span>
                        <div class="txt">
                            <strong>Crea un respaldo</strong>
                            <span>Antes de cualquier cambio.
                                <a href="<?= e(url('super-admin/respaldos')) ?>">Ir a Respaldos</a></span>
                        </div>
                    </div>

                    <div class="next-step">
                        <span class="num">2</span>
                        <div class="txt">
                            <strong>Actualiza los archivos</strong>
                            <span>
                                En el panel de tu hosting: <strong>Avanzado → GIT</strong> y pulsa
                                <em>Deploy</em>, o sube los archivos por el Administrador de archivos.
                                No toques <code>config/</code>, <code>storage/</code> ni
                                <code>public/uploads/</code>.
                            </span>
                        </div>
                    </div>

                    <div class="next-step">
                        <span class="num">3</span>
                        <div class="txt">
                            <strong>Ejecuta las migraciones</strong>
                            <span>Si la versión nueva cambia la base de datos.
                                <a href="<?= e(url('super-admin/migraciones')) ?>">Ir a Migraciones</a></span>
                        </div>
                    </div>

                    <div class="next-step">
                        <span class="num">4</span>
                        <div class="txt">
                            <strong>Comprueba la versión</strong>
                            <span>Debe subir el número que aparece arriba como «Versión instalada».</span>
                        </div>
                    </div>
                </div>

                <?php if ($status['github_enabled'] && $status['github_token']): ?>
                    <hr class="divider">
                    <p class="small muted mb-2">
                        Aunque el despliegue automático no esté disponible, sí puedes consultar
                        GitHub para saber si hay algo nuevo.
                    </p>
                    <form method="post" action="<?= e(url('super-admin/github/buscar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-light btn-sm btn-block">
                            <?= icon('refresh', 15) ?> Buscar actualizaciones en GitHub
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
