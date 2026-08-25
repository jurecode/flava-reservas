<?php
/**
 * Ruta: /app/Views/superadmin/system/info.php
 * Información del servidor. Nunca se expone phpinfo() completo (spec §142).
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Información del servidor</h1>
        <p class="sub">Sólo visible para el Súper Administrador.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('super-admin/rutas')) ?>" class="btn btn-ghost btn-sm">Ver rutas</a>
        <a href="<?= e(url('super-admin')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">PHP</h2>
        <div class="sys-row"><span class="k">Versión</span><span class="v"><?= e($php['version']) ?></span></div>
        <div class="sys-row"><span class="k">SAPI</span><span class="v"><?= e($php['sapi']) ?></span></div>
        <div class="sys-row"><span class="k">Memoria</span><span class="v"><?= e($php['memory_limit']) ?></span></div>
        <div class="sys-row"><span class="k">Tiempo máx. ejecución</span><span class="v"><?= e($php['max_execution']) ?>s</span></div>
        <div class="sys-row"><span class="k">Subida máxima</span><span class="v"><?= e($php['upload_max']) ?></span></div>
        <div class="sys-row"><span class="k">POST máximo</span><span class="v"><?= e($php['post_max']) ?></span></div>
        <div class="sys-row"><span class="k">Zona horaria</span><span class="v"><?= e($php['timezone']) ?></span></div>
    </div>

    <div class="card">
        <h2 style="font-size:1rem">Extensiones</h2>
        <?php foreach ($php['extensions'] as $name => $loaded): ?>
            <div class="sys-row">
                <span class="k mono"><?= e($name) ?></span>
                <span class="v"><?= $loaded ? '<span class="dot dot-ok"></span>Disponible' : '<span class="dot dot-err"></span>Ausente' ?></span>
            </div>
        <?php endforeach; ?>

        <p class="tiny muted mt-2 mb-0">
            <code>pdo_mysql</code> y <code>mbstring</code> son obligatorias. <code>curl</code> se usa para GitHub y
            <code>sodium</code>/<code>openssl</code> para cifrar secretos.
        </p>
    </div>

    <div class="card">
        <h2 style="font-size:1rem">Aplicación</h2>
        <div class="sys-row"><span class="k">Versión</span><span class="v">v<?= e($status['version']) ?> «<?= e($status['codename']) ?>»</span></div>
        <div class="sys-row"><span class="k">Entorno</span><span class="v"><?= e($status['environment']) ?></span></div>
        <div class="sys-row"><span class="k">Servidor web</span><span class="v small"><?= e($status['server']) ?></span></div>
        <div class="sys-row"><span class="k">Base de datos</span><span class="v"><?= e($status['database']) ?></span></div>
        <div class="sys-row"><span class="k">Ruta del repositorio</span><span class="v mono tiny"><?= e($status['repo_path']) ?></span></div>
        <div class="sys-row"><span class="k">Cifrado listo</span><span class="v"><?= $status['crypto_ready'] ? '<span class="dot dot-ok"></span>Sí' : '<span class="dot dot-err"></span>Falta APP_KEY' ?></span></div>
    </div>

    <div class="card">
        <h2 style="font-size:1rem">Funciones deshabilitadas</h2>
        <?php if (trim((string) $php['disable_functions']) === ''): ?>
            <p class="small muted mb-0">Ninguna. El servidor permite ejecutar comandos del sistema.</p>
        <?php else: ?>
            <p class="tiny mono muted mb-0" style="word-break:break-word"><?= e($php['disable_functions']) ?></p>
        <?php endif; ?>

        <p class="small muted mt-2 mb-0">
            Si <code>proc_open</code> está bloqueado, las operaciones Git desde el panel no estarán disponibles
            y el despliegue deberá hacerse por otra vía.
        </p>
    </div>
</div>

<?php View::stop(); ?>
