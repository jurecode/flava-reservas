<?php
/**
 * Ruta: /app/Views/superadmin/github/index.php
 * Panel «Sistema → GitHub y Actualizaciones» (spec §104).
 *
 * IMPORTANTE: el token JAMÁS se imprime en esta página; sólo su pista.
 */

use App\Models\Deployment;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>GitHub &amp; Deploy</h1>
        <p class="sub">Repositorio central, versiones y actualizaciones controladas.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('super-admin/despliegues')) ?>" class="btn btn-ghost btn-sm">Ver despliegues</a>
    </div>
</div>

<!-- Flujo oficial de trabajo (spec §103, §137, §148) -->
<div class="card card-dark mb-3">
    <div class="row-between gap-lg">
        <div class="flow">
            <span class="flow-node">Local</span>
            <?= icon('arrow-right', 15) ?>
            <span class="flow-node">GitHub</span>
            <?= icon('arrow-right', 15) ?>
            <span class="flow-node">Producción</span>
        </div>
        <p class="small mb-0" style="color:rgba(255,253,245,.65);max-width:420px">
            Desarrolla en local, sube con <code>git push</code> y actualiza el servidor desde aquí.
            Producción nunca se edita a mano.
        </p>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="stack">
        <!-- Estado actual -->
        <div class="card">
            <h2 style="font-size:1rem">Estado actual</h2>

            <div class="sys-row">
                <span class="k">Repositorio</span>
                <span class="v">
                    <?php if ($github['repo_url']): ?>
                        <a href="<?= e($github['repo_url']) ?>" target="_blank" rel="noopener"><?= e($github['owner'] . '/' . $github['repository']) ?></a>
                    <?php else: ?>
                        <span class="muted">Sin configurar</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="sys-row"><span class="k">Branch</span><span class="v mono"><?= e($github['branch']) ?></span></div>
            <div class="sys-row"><span class="k">Servidor</span><span class="v"><?= e(ucfirst($status['environment'])) ?></span></div>
            <div class="sys-row"><span class="k">Versión instalada</span><span class="v">v<?= e($status['version']) ?></span></div>
            <div class="sys-row">
                <span class="k">Último commit</span>
                <span class="v mono"><?= e($status['current_commit'] ?? '—') ?></span>
            </div>
            <?php if (!empty($status['last_commit'])): ?>
                <div class="sys-row"><span class="k">Mensaje</span><span class="v small"><?= e(\App\Support\Str::limit($status['last_commit']['message'], 60)) ?></span></div>
            <?php endif; ?>
            <div class="sys-row"><span class="k">Última verificación</span><span class="v small"><?= e($status['last_check'] ?: '—') ?></span></div>
            <div class="sys-row"><span class="k">Última sincronización</span><span class="v small"><?= e($status['last_sync'] ?: '—') ?></span></div>
            <div class="sys-row">
                <span class="k">Git instalado</span>
                <span class="v">
                    <?= $status['git_available']
                        ? '<span class="dot dot-ok"></span>Sí · ' . e($status['git_version'])
                        : '<span class="dot dot-err"></span>No disponible' ?>
                </span>
            </div>
            <div class="sys-row">
                <span class="k">Estado del árbol</span>
                <span class="v">
                    <?php if (!$status['is_repository']): ?>
                        <span class="muted">Sin repositorio</span>
                    <?php elseif ($status['local_changes']['clean']): ?>
                        <span class="dot dot-ok"></span>Limpio
                    <?php else: ?>
                        <span class="dot dot-warn"></span><?= count($status['local_changes']['files']) ?> archivo(s) modificados
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if (!$status['shell_available'] || !$status['git_available']): ?>
            <div class="alert alert-warning">
                <?= icon('zap', 17) ?>
                <div>
                    <strong>Este servidor no permite operaciones Git desde PHP.</strong>
                    Podrás consultar el repositorio y ejecutar migraciones desde el panel, pero la actualización
                    de archivos deberá hacerse por otra vía (SSH, cPanel Git™ o subida manual).
                </div>
            </div>
        <?php endif; ?>

        <?php if ($status['is_repository'] && !$status['local_changes']['clean']): ?>
            <div class="alert alert-warning">
                <?= icon('alert', 17) ?>
                <div>
                    <strong>Existen modificaciones locales en el servidor.</strong>
                    No se sobrescribirán automáticamente. Revísalas antes de actualizar:
                    <div class="mono tiny mt-1" style="max-height:110px;overflow:auto">
                        <?php foreach (array_slice($status['local_changes']['files'], 0, 25) as $file): ?>
                            <?= e($file) ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Operaciones permitidas: lista cerrada, sin comandos libres (spec §113) -->
        <div class="card">
            <h2 style="font-size:1rem">Operaciones</h2>
            <p class="small muted">Acciones predefinidas. El sistema no permite ejecutar comandos arbitrarios.</p>

            <div class="row gap-sm mt-2">
                <form method="post" action="<?= e(url('super-admin/github/probar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light btn-sm" <?= $github['has_token'] ? '' : 'disabled' ?>>
                        <?= icon('zap', 15) ?> Probar conexión
                    </button>
                </form>

                <form method="post" action="<?= e(url('super-admin/github/buscar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-dark btn-sm" <?= $github['enabled'] ? '' : 'disabled' ?>>
                        <?= icon('refresh', 15) ?> Buscar actualizaciones
                    </button>
                </form>

                <a href="<?= e(url('super-admin/despliegues')) ?>" class="btn btn-primary btn-sm"><?= icon('rocket', 15) ?> Ir a desplegar</a>
            </div>
        </div>

        <!-- Resultado de la prueba de conexión -->
        <?php if (!empty($connection)): ?>
            <div class="card">
                <h2 style="font-size:1rem" class="row gap-sm">
                    <?= $connection['ok'] ? icon('check-circle', 17) : icon('x-circle', 17) ?>
                    <?= $connection['ok'] ? 'Conexión correcta' : 'Problemas de conexión' ?>
                </h2>
                <?php foreach ($connection['checks'] as $check): ?>
                    <div class="sys-row">
                        <span class="k"><?= $check['ok'] ? '<span class="dot dot-ok"></span>' : '<span class="dot dot-err"></span>' ?></span>
                        <span class="v small" style="text-align:left;flex:1"><?= e($check['detail']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Resultado de la búsqueda de actualizaciones -->
        <?php if (!empty($updates)): ?>
            <div class="card <?= $updates['available'] ? 'card-accent' : '' ?>">
                <h2 style="font-size:1rem" class="row gap-sm">
                    <?= $updates['available'] ? icon('trending-up', 17) : icon('check-circle', 17) ?>
                    <?= $updates['available'] ? 'Actualización disponible' : 'Sistema actualizado' ?>
                </h2>

                <div class="sys-row"><span class="k">Instalado</span><span class="v mono"><?= e($updates['local'] ?? '—') ?></span></div>
                <div class="sys-row"><span class="k">GitHub</span><span class="v mono"><?= e($updates['remote'] ?? '—') ?></span></div>

                <?php if (!empty($updates['commits'])): ?>
                    <div class="label mt-2">Cambios</div>
                    <div style="max-height:210px;overflow-y:auto">
                        <?php foreach ($updates['commits'] as $commit): ?>
                            <div class="commit-item">
                                <span class="commit-hash"><?= e($commit['short'] ?? '') ?></span>
                                <div class="grow">
                                    <div class="small"><?= e($commit['message'] ?? '') ?></div>
                                    <div class="tiny muted"><?= e($commit['author'] ?? '') ?> · <?= e(substr((string) ($commit['date'] ?? ''), 0, 10)) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($updates['has_migrations'])): ?>
                    <div class="alert alert-info mt-2 mb-0">
                        <?= icon('database', 17) ?>
                        <div>Esta actualización incluye <strong>migraciones de base de datos</strong>. Se ejecutarán después de aplicar los archivos, tras crear el respaldo.</div>
                    </div>
                <?php endif; ?>

                <?php if ($updates['available']): ?>
                    <a href="<?= e(url('super-admin/despliegues')) ?>" class="btn btn-primary btn-block mt-2">Crear respaldo y actualizar</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="stack">
        <!-- Configuración del repositorio -->
        <div class="card">
            <h2 style="font-size:1rem">Configuración del repositorio</h2>

            <form method="post" action="<?= e(url('super-admin/github/config')) ?>" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="github_owner">GitHub owner</label>
                    <input class="input" type="text" id="github_owner" name="github_owner" maxlength="100"
                           value="<?= e($github['owner']) ?>" placeholder="flavastudio">
                    <?php if ($m = error_for('github_owner')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="github_repository">Repositorio</label>
                    <input class="input" type="text" id="github_repository" name="github_repository" maxlength="120"
                           value="<?= e($github['repository']) ?>" placeholder="flava-web">
                    <?php if ($m = error_for('github_repository')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="github_branch">Rama de producción</label>
                    <input class="input" type="text" id="github_branch" name="github_branch" maxlength="100"
                           value="<?= e($github['branch']) ?>" placeholder="main" list="branchOptions">
                    <?php if (!empty($branches)): ?>
                        <datalist id="branchOptions">
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= e($branch) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    <?php endif; ?>
                    <div class="field-hint">Preparado para develop → staging → main.</div>
                </div>

                <label class="check mb-2">
                    <input type="checkbox" name="github_enabled" value="1" <?= $github['enabled'] ? 'checked' : '' ?>>
                    <span><strong>Integración activa</strong></span>
                </label>

                <label class="check mb-2">
                    <input type="checkbox" name="deploy_auto_backup" value="1" <?= setting('deploy_auto_backup', true) ? 'checked' : '' ?>>
                    <span><strong>Respaldar antes de actualizar</strong><br><span class="small muted">Recomendado</span></span>
                </label>

                <label class="check mb-2">
                    <input type="checkbox" name="deploy_maintenance" value="1" <?= setting('deploy_maintenance', true) ? 'checked' : '' ?>>
                    <span><strong>Activar mantención durante el deploy</strong></span>
                </label>

                <button type="submit" class="btn btn-primary btn-block">Guardar configuración</button>
            </form>
        </div>

        <!-- Token -->
        <div class="card">
            <h2 style="font-size:1rem">Personal Access Token</h2>

            <?php if ($github['env_token']): ?>
                <div class="alert alert-info">
                    <?= icon('lock', 17) ?>
                    <div>El token está definido por <strong>variable de entorno</strong> (<code>GITHUB_TOKEN</code>), la opción más segura. No es editable desde aquí.</div>
                </div>
            <?php else: ?>
                <?php if ($github['has_token']): ?>
                    <div class="sys-row">
                        <span class="k">Token conectado</span>
                        <span class="v mono"><?= e($github['token_hint'] ?: 'configurado') ?></span>
                    </div>
                <?php else: ?>
                    <p class="small muted">Aún no hay un token configurado.</p>
                <?php endif; ?>

                <?php if (!$crypto_ready): ?>
                    <div class="alert alert-error mt-2">
                        <?= icon('alert', 17) ?>
                        <div>
                            Configura <code>APP_KEY</code> antes de guardar el token.
                            Genérala con <code>php bin/flava key:generate</code>.
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('super-admin/github/token')) ?>" class="mt-2" data-once>
                    <?= csrf_field() ?>

                    <div class="field">
                        <label class="label" for="github_token">Nuevo token</label>
                        <input class="input mono" type="password" id="github_token" name="github_token"
                               placeholder="github_pat_..." autocomplete="off" <?= $crypto_ready ? '' : 'disabled' ?>>
                        <div class="field-hint">
                            Usa un <strong>fine-grained token</strong> con permisos mínimos:
                            <code>Contents: Read</code> y <code>Metadata: Read</code>.
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="confirm_password">Confirma tu contraseña</label>
                        <input class="input" type="password" id="confirm_password" name="confirm_password"
                               autocomplete="current-password" <?= $crypto_ready ? '' : 'disabled' ?>>
                    </div>

                    <button type="submit" class="btn btn-dark btn-block" <?= $crypto_ready ? '' : 'disabled' ?>>
                        <?= $github['has_token'] ? 'Actualizar token' : 'Guardar token' ?>
                    </button>
                </form>

                <?php if ($github['has_token']): ?>
                    <form method="post" action="<?= e(url('super-admin/github/token/eliminar')) ?>" class="mt-2"
                          data-confirm="¿Eliminar el token guardado? El sistema dejará de consultar GitHub.">
                        <?= csrf_field() ?>
                        <input class="input mb-2" type="password" name="confirm_password" placeholder="Confirma tu contraseña" autocomplete="current-password">
                        <button type="submit" class="btn btn-ghost btn-sm btn-block">Eliminar token</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

            <hr class="divider">

            <p class="tiny muted mb-0">
                <?= icon('lock', 13) ?> El token se almacena <strong>cifrado</strong> (libsodium o AES-256-GCM) con una clave que vive fuera
                del webroot. Nunca se muestra completo, no viaja al navegador ni queda escrito en los logs.
                El servidor sólo necesita permisos de <strong>lectura</strong>: los permisos de escritura quedan
                para tu entorno local.
            </p>
        </div>

        <div class="card card-flush">
            <div class="card-head"><h2>Últimos despliegues</h2></div>
            <div class="card-body stack-sm">
                <?php if ($deployments === []): ?>
                    <p class="small muted mb-0">Sin despliegues registrados.</p>
                <?php else: ?>
                    <?php foreach ($deployments as $deployment): ?>
                        <div class="row-between small" style="padding:7px 0;border-bottom:1px solid var(--line)">
                            <span>
                                <span class="commit-hash"><?= e(substr((string) $deployment['commit_hash'], 0, 7) ?: '—') ?></span>
                                <span class="muted"><?= e(substr((string) $deployment['started_at'], 0, 16)) ?></span>
                            </span>
                            <span class="badge <?= e(Deployment::statusBadge($deployment['status'])) ?>"><?= e(Deployment::statusLabel($deployment['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php View::stop(); ?>
