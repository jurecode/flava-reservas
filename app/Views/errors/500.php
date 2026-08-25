<?php
/**
 * Ruta: /app/Views/errors/500.php
 */

use Core\View;

View::set('bodyClass', 'honeycomb');
View::layout('blank');
View::start('content');
?>
<div class="error-shell">
    <div>
        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="46" height="52" style="margin:0 auto 20px">
        <div class="error-code">500</div>
        <h1>Algo salió mal</h1>
        <p><?= e($message ?? 'Tuvimos un problema técnico. Ya quedó registrado y lo estamos revisando.') ?></p>
        <div class="row gap-sm" style="justify-content:center">
            <a href="<?= e(url('/')) ?>" class="btn btn-primary">Ir al inicio</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
