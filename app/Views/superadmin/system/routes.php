<?php
/**
 * Ruta: /app/Views/superadmin/system/routes.php
 * Mapa de rutas registradas: útil para depurar permisos.
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Rutas registradas</h1>
        <p class="sub"><?= count($routes) ?> ruta(s) · definidas en <code>/routes/web.php</code> y <code>/routes/api.php</code></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('super-admin/sistema')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="card card-flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Método</th><th>URI</th><th>Controlador</th><th>Middleware</th></tr>
            </thead>
            <tbody>
                <?php foreach ($routes as $route): ?>
                    <tr>
                        <td>
                            <span class="badge <?= $route['method'] === 'GET' ? 'badge-confirmed' : 'badge-progress' ?>"><?= e($route['method']) ?></span>
                        </td>
                        <td class="mono small"><?= e($route['uri']) ?></td>
                        <td class="small muted"><?= e($route['handler']) ?></td>
                        <td class="tiny muted"><?= e($route['middleware'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php View::stop(); ?>
