<?php
/**
 * Ruta: /app/Views/errors/404.php
 */

use Core\View;

View::set('bodyClass', 'honeycomb');
View::layout('blank');
View::start('content');
?>
<div class="error-shell">
    <div>
        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="46" height="52" style="margin:0 auto 20px">
        <div class="error-code">404</div>
        <h1>Esta página no existe</h1>
        <p><?= e($message ?? 'Puede que el enlace esté roto o que la reserva ya no esté disponible.') ?></p>
        <div class="row gap-sm" style="justify-content:center">
            <a href="<?= e(url('/')) ?>" class="btn btn-primary">Ir al inicio</a>
            <a href="<?= e(url('reservar')) ?>" class="btn btn-ghost" style="color:#FFFDF5;border-color:rgba(255,255,255,.22)">Reservar hora</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
