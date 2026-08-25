<?php
/**
 * Ruta: /app/Views/superadmin/index.php
 * Estado general del sistema (spec §101).
 */

use App\Models\Deployment;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Sistema</h1>
        <p class="sub">Flava Studio v<?= e($status['version']) ?> «<?= e($status['codename']) ?>» · entorno <?= e($status['environment']) ?></p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= e(url('super-admin/cache/limpiar')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Limpiar cache</button>
        </form>
        <form method="post" action="<?= e(url('super-admin/mantencion')) ?>"
              data-confirm="<?= $status['maintenance'] ? '¿Desactivar el modo mantención y volver a publicar el sitio?' : '¿Activar el modo mantención? Los clientes no podrán reservar.' ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm <?= $status['maintenance'] ? 'btn-success' : 'btn-dark' ?>">
                <?= $status['maintenance'] ? 'Desactivar mantención' : 'Activar mantención' ?>
            </button>
        </form>
    </div>
</div>

<?php if ($status['maintenance']): ?>
    <div class="alert alert-warning">
        <?= icon('zap', 17) ?>
        <div><strong>Modo mantención activo.</strong> Los clientes ven la página de mantención; tú conservas el acceso completo.</div>
    </div>
<?php endif; ?>

<?php if (!$status['crypto_ready']): ?>
    <div class="alert alert-error">
        <?= icon('alert', 17) ?>
        <div>
            <strong>APP_KEY no configurada.</strong>
            Sin ella no es posible almacenar secretos cifrados (como el token de GitHub).
            Genera una con <code>php bin/flava key:generate</code> y guárdala en <code>/.env</code> o
            <code>/config/secrets.php</code>, fuera del webroot.
        </div>
    </div>
<?php endif; ?>

<?php if ($status['pending_migrations'] !== []): ?>
    <div class="alert alert-warning">
        <?= icon('database', 17) ?>
        <div>
            <strong><?= count($status['pending_migrations']) ?> migración(es) pendiente(s).</strong>
            <a href="<?= e(url('super-admin/migraciones')) ?>" class="bold">Revisar y ejecutar</a>
        </div>
    </div>
<?php endif; ?>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Versión</span>
            <span class="ico-box"><?= icon('tag', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.4rem">v<?= e($status['version']) ?></div>
        <div class="d"><?= e($status['codename']) ?></div>
    </div>
    <div class="kpi <?= $status['git_available'] ? 'kpi-ok' : 'kpi-warn' ?>">
        <div class="kpi-head">
            <span class="k">Git</span>
            <span class="ico-box"><?= icon('terminal', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.4rem"><?= $status['git_available'] ? e($status['git_version']) : 'No' ?></div>
        <div class="d"><?= $status['is_repository'] ? 'Repositorio activo' : 'Sin repositorio' ?></div>
    </div>
    <div class="kpi <?= $status['github_enabled'] && $status['github_token'] ? 'kpi-ok' : 'kpi-warn' ?>">
        <div class="kpi-head">
            <span class="k">GitHub</span>
            <span class="ico-box"><?= icon('github', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.4rem"><?= $status['github_enabled'] ? 'Activo' : 'Inactivo' ?></div>
        <div class="d"><?= e($status['github_repo'] ?: 'Sin configurar') ?></div>
    </div>
    <div class="kpi kpi-info">
        <div class="k">Commit instalado</div>
        <div class="v mono" style="font-size:1.25rem"><?= e($status['current_commit'] ?? '—') ?></div>
        <div class="d"><?= e($status['current_branch'] ?? 'sin rama') ?></div>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Entorno</h2>
        <div class="sys-row"><span class="k">PHP</span><span class="v"><?= e($status['php_version']) ?></span></div>
        <div class="sys-row"><span class="k">Servidor</span><span class="v small"><?= e($status['server']) ?></span></div>
        <div class="sys-row"><span class="k">Base de datos</span><span class="v"><?= e($status['database']) ?></span></div>
        <div class="sys-row"><span class="k">Zona horaria</span><span class="v"><?= e($status['timezone']) ?></span></div>
        <div class="sys-row"><span class="k">Estrategia de deploy</span><span class="v"><?= e(strtoupper($status['strategy'])) ?></span></div>
        <div class="sys-row">
            <span class="k">Comandos del sistema</span>
            <span class="v"><?= $status['shell_available'] ? '<span class="dot dot-ok"></span>Disponibles' : '<span class="dot dot-warn"></span>Bloqueados' ?></span>
        </div>

        <a href="<?= e(url('super-admin/sistema')) ?>" class="btn btn-ghost btn-sm mt-2">Ver detalle del servidor</a>
    </div>

    <div class="card">
        <h2 style="font-size:1rem">Permisos de escritura</h2>
        <?php foreach ($status['writable'] as $path => $writable): ?>
            <div class="sys-row">
                <span class="k mono small"><?= e($path) ?></span>
                <span class="v">
                    <?= $writable ? '<span class="dot dot-ok"></span>OK' : '<span class="dot dot-err"></span>Sin permiso' ?>
                </span>
            </div>
        <?php endforeach; ?>

        <p class="small muted mt-2 mb-0">
            Estas carpetas deben ser escribibles (755 o 775 según el hosting) para logs, respaldos y subida de imágenes.
        </p>
    </div>

    <div class="card card-flush">
        <div class="card-head">
            <h2>Últimos despliegues</h2>
            <a href="<?= e(url('super-admin/despliegues')) ?>" class="btn btn-xs btn-ghost">Ver todos</a>
        </div>
        <div class="card-body stack-sm">
            <?php if ($deployments === []): ?>
                <p class="small muted mb-0">Todavía no se registran despliegues.</p>
            <?php else: ?>
                <?php foreach ($deployments as $deployment): ?>
                    <div class="row-between small" style="padding:8px 0;border-bottom:1px solid var(--line)">
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

    <div class="card">
        <h2 style="font-size:1rem">Cambios recientes</h2>
        <?php if ($changelog === []): ?>
            <p class="small muted mb-0">No se encontró <code>CHANGELOG.md</code> en la raíz del proyecto.</p>
        <?php else: ?>
            <?php foreach ($changelog as $entry): ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                    <strong><?= e($entry['heading']) ?></strong>
                    <div class="small muted" style="white-space:pre-line"><?= e(\App\Support\Str::limit($entry['body'], 320)) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php View::stop(); ?>
