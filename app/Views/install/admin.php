<?php
/**
 * Ruta: /app/Views/install/admin.php
 * PASO 4 — Cuenta del Súper Administrador.
 */

use Core\View;

View::layout('install');
View::start('content');
?>

<div class="install-head">
    <h1>Tu cuenta de administrador</h1>
    <p>Con esta cuenta entras al panel. Tendrá el rol de Súper Administrador: acceso completo.</p>
</div>

<div class="hosting-hint">
    <?= icon('shield', 16) ?>
    <div>
        Al crear tu cuenta <strong>eliminamos el usuario de ejemplo</strong> que trae el sistema
        (<code>admin@flava.cl</code>). Así no queda ninguna credencial conocida en tu instalación.
    </div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('instalar/administrador')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="first_name">Nombre</label>
                <input class="input <?= error_for('first_name') ? 'is-invalid' : '' ?>" type="text" id="first_name"
                       name="first_name" required maxlength="80" autofocus value="<?= e(old('first_name')) ?>">
                <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="last_name">Apellido</label>
                <input class="input <?= error_for('last_name') ? 'is-invalid' : '' ?>" type="text" id="last_name"
                       name="last_name" required maxlength="80" value="<?= e(old('last_name')) ?>">
                <?php if ($m = error_for('last_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="email">Email</label>
                <div class="input-group">
                    <?= icon('mail', 16) ?>
                    <input class="input <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email"
                           name="email" required maxlength="150" autocomplete="username" value="<?= e(old('email')) ?>">
                </div>
                <div class="field-hint">Con este email inicias sesión.</div>
                <?php if ($m = error_for('email')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="phone">Teléfono <span class="muted">(opcional)</span></label>
                <div class="input-group">
                    <?= icon('phone', 16) ?>
                    <input class="input" type="tel" id="phone" name="phone" maxlength="20" value="<?= e(old('phone')) ?>">
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="password">Contraseña</label>
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
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <?= icon('user-check', 16) ?> Crear mi cuenta
        </button>
    </form>
</div>

<div class="install-nav">
    <a href="<?= e(url('instalar/esquema')) ?>" class="btn btn-ghost"><?= icon('arrow-left', 15) ?> Atrás</a>
</div>

<?php View::stop(); ?>
