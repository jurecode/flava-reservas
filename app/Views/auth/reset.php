<?php
/**
 * Ruta: /app/Views/auth/reset.php
 */

use Core\View;

View::layout('blank');
View::start('content');
?>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" class="mark" width="54" height="61">
            <h1>Nueva contraseña</h1>
            <p>Define una contraseña segura.</p>
        </div>

        <?php require View::path('components.flash'); ?>

        <form method="post" action="<?= e(url('restablecer/' . $token)) ?>" data-once>
            <?= csrf_field() ?>

            <div class="field">
                <label class="label" for="password">Contraseña nueva</label>
                <input class="input <?= error_for('password') ? 'is-invalid' : '' ?>" type="password"
                       id="password" name="password" required minlength="8" autocomplete="new-password">
                <?php if ($m = error_for('password')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="password_confirmation">Repite la contraseña</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                       required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Guardar</button>
        </form>
    </div>
</div>
<?php View::stop(); ?>
