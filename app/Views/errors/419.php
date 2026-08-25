<?php
/**
 * Ruta: /app/Views/errors/419.php
 */

use Core\View;

View::set('bodyClass', 'honeycomb');
View::layout('blank');
View::start('content');
?>
<div class="error-shell">
    <div>
        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="46" height="52" style="margin:0 auto 20px">
        <div class="error-code">419</div>
        <h1>La sesión expiró</h1>
        <p><?= e($message ?? 'Por seguridad cerramos la sesión del formulario. Vuelve atrás y envíalo de nuevo.') ?></p>
        <div class="row gap-sm" style="justify-content:center">
            <a href="javascript:history.back()" class="btn btn-primary">Volver atrás</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
