<?php
/**
 * Ruta: /app/Views/errors/403.php
 */

use Core\View;

View::set('bodyClass', 'honeycomb');
View::layout('blank');
View::start('content');
?>
<div class="error-shell">
    <div>
        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="46" height="52" style="margin:0 auto 20px">
        <div class="error-code">403</div>
        <h1>Acceso denegado</h1>
        <p><?= e($message ?? 'No tienes permisos para entrar aquí. Si crees que es un error, contacta al administrador.') ?></p>
        <div class="row gap-sm" style="justify-content:center">
            <a href="<?= e(url('/')) ?>" class="btn btn-primary">Ir al inicio</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
