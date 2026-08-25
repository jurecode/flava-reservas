<?php
/**
 * Ruta: /app/Views/auth/profile.php
 */

use App\Support\Role;
use Core\View;

View::layout('panel');
View::start('content');
?>
<div class="page-head">
    <div>
        <h1>Mi cuenta</h1>
        <p class="sub"><?= e(Role::label($user['role'])) ?> · <?= e($user['email']) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('cuenta/password')) ?>" class="btn btn-ghost btn-sm">Cambiar contraseña</a>
    </div>
</div>

<div class="card" style="max-width:640px">
    <form method="post" action="<?= e(url('cuenta')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="first_name">Nombre</label>
                <input class="input" type="text" id="first_name" name="first_name" required
                       value="<?= e(old('first_name', $user['first_name'])) ?>" maxlength="80">
            </div>

            <div class="field">
                <label class="label" for="last_name">Apellido</label>
                <input class="input" type="text" id="last_name" name="last_name" required
                       value="<?= e(old('last_name', $user['last_name'])) ?>" maxlength="80">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input" type="email" id="email" name="email" required
                       value="<?= e(old('email', $user['email'])) ?>" maxlength="150">
            </div>

            <div class="field">
                <label class="label" for="phone">Teléfono</label>
                <input class="input" type="tel" id="phone" name="phone"
                       value="<?= e(old('phone', $user['phone'])) ?>" maxlength="20">
            </div>
        </div>

        <div class="row-between mt-2">
            <span class="small muted">
                <?php if (!empty($user['last_login_at'])): ?>
                    Último ingreso: <?= e($user['last_login_at']) ?>
                <?php endif; ?>
            </span>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>
<?php View::stop(); ?>
