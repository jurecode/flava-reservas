<?php
/**
 * Ruta: /app/Views/superadmin/system/backups.php
 */

use App\Services\System\BackupService;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Respaldos</h1>
        <p class="sub">Se guardan fuera del directorio público, en <code><?= e(str_replace(BASE_PATH, '', $path)) ?></code></p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= e(url('super-admin/respaldos/crear')) ?>" class="row gap-sm" data-once>
            <?= csrf_field() ?>
            <input class="input" type="text" name="label" placeholder="Etiqueta (opcional)" maxlength="40" style="padding:8px 12px;font-size:.86rem">
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('save', 15) ?> Crear respaldo</button>
        </form>
    </div>
</div>

<div class="alert alert-info">
    <?= icon('info', 17) ?>
    <div class="small">
        Cada respaldo incluye un volcado completo de la base de datos y un <code>metadata.json</code> con la versión
        instalada. Se usa <code>mysqldump</code> si está disponible; si no, un volcado equivalente hecho desde PHP
        (compatible con hostings compartidos).
    </div>
</div>

<div class="card card-flush">
    <div class="card-head">
        <h2>Respaldos disponibles</h2>
        <form method="post" action="<?= e(url('super-admin/respaldos/limpiar')) ?>" class="row gap-sm"
              data-confirm="¿Eliminar los respaldos antiguos y conservar sólo los más recientes?">
            <?= csrf_field() ?>
            <input class="input" type="number" name="keep" value="10" min="3" max="50" style="width:82px;padding:7px 10px;font-size:.84rem">
            <button type="submit" class="btn btn-xs btn-ghost">Conservar N y limpiar</button>
        </form>
    </div>

    <?php if ($backups === []): ?>
        <?php $icon = 'save'; $message = 'Aún no hay respaldos'; $hint = 'Crea uno antes de cualquier cambio importante.'; require View::path('components.empty'); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Respaldo</th><th>Creado</th><th>Versión</th><th>Origen</th><th class="right">Tamaño</th><th>BD</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td class="mono tiny"><?= e($backup['name']) ?></td>
                            <td class="small nowrap"><?= e($backup['created_at']) ?></td>
                            <td class="small">v<?= e($backup['metadata']['version'] ?? '—') ?></td>
                            <td class="small muted"><?= e($backup['metadata']['label'] ?? 'manual') ?></td>
                            <td class="right small"><?= e(BackupService::humanSize($backup['size'])) ?></td>
                            <td>
                                <?php if (!empty($backup['metadata']['has_dump'])): ?>
                                    <span class="badge badge-checkedin">Incluida</span>
                                <?php else: ?>
                                    <span class="badge badge-noshow">Sin volcado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card card-muted mt-3">
    <h3 style="font-size:.95rem">Cómo restaurar un respaldo</h3>
    <ol class="small muted" style="padding-left:18px;margin:0">
        <li>Descarga por FTP/SSH la carpeta del respaldo desde <code>/storage/backups</code>.</li>
        <li>Importa <code>database.sql</code> en phpMyAdmin sobre una base <strong>vacía</strong>.</li>
        <li>Apunta <code>/config/database.php</code> o <code>/.env</code> a esa base.</li>
    </ol>
    <p class="tiny muted mt-2 mb-0">
        La restauración no es automática a propósito: reemplazar datos de producción debe ser una decisión consciente.
    </p>
</div>

<?php View::stop(); ?>
