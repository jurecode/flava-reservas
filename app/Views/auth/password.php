<?php
/**
 * Ruta: /app/Views/auth/password.php
 * Cambio de contraseña (incluye el obligatorio del primer ingreso).
 */

use Core\View;

if ($forced) {
    View::layout('blank');
} else {
    View::layout('panel');
}

View::start('content');
?>

<?php if ($forced): ?>
<div class="auth-shell">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" class="mark" width="54" height="61">
            <h1>Define tu contraseña</h1>
            <p>Por seguridad, cambia la contraseña temporal antes de continuar.</p>
        </div>

        <?php require View::path('components.flash'); ?>
<?php else: ?>
    <div class="page-head">
        <div>
            <h1>Cambiar contraseña</h1>
            <p class="sub">Usa una contraseña que no utilices en otros servicios.</p>
        </div>
    </div>
    <div class="card" style="max-width:520px">
<?php endif; ?>

        <form method="post" action="<?= e(url('cuenta/password')) ?>" data-once>
            <?= csrf_field() ?>

            <?php if (!$forced): ?>
                <div class="field">
                    <label class="label" for="current_password">Contraseña actual</label>
                    <input class="input <?= error_for('current_password') ? 'is-invalid' : '' ?>" type="password"
                           id="current_password" name="current_password" required autocomplete="current-password">
                    <?php if ($m = error_for('current_password')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="field">
                <label class="label" for="password">Contraseña nueva</label>
                <input class="input <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password"
                       name="password" required minlength="8" autocomplete="new-password">
                <div class="field-hint">Mínimo 8 caracteres, con letras y números.</div>
                <?php if ($m = error_for('password')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="password_confirmation">Repite la contraseña</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                       required minlength="8" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-2">Guardar contraseña</button>
        </form>

<?php if ($forced): ?>
    </div>
</div>
<?php else: ?>
    </div>
<?php endif; ?>

<?php View::stop(); ?>
