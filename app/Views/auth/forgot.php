<?php
/**
 * Ruta: /app/Views/auth/forgot.php
 */

use Core\View;

View::layout('blank');
View::start('content');
?>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" class="mark" width="54" height="61">
            <h1>Recuperar acceso</h1>
            <p>Te enviaremos instrucciones a tu email.</p>
        </div>

        <?php require View::path('components.flash'); ?>

        <form method="post" action="<?= e(url('recuperar')) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" required autofocus
                       value="<?= e(old('email')) ?>" placeholder="tu@flava.cl">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Enviar instrucciones</button>
        </form>

        <div class="center mt-3">
            <a href="<?= e(url('login')) ?>" class="small muted">← Volver al login</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
