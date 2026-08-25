<?php
/**
 * Ruta: /app/Views/admin/users/form.php
 */

use App\Support\Role;
use Core\View;

View::layout('panel');
View::start('content');

$isEdit = $user !== null;
$action = $isEdit ? url('admin/usuarios/' . $user['id']) : url('admin/usuarios');
?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <p class="sub">Sólo puedes asignar roles de nivel igual o inferior al tuyo.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/usuarios')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="card" style="max-width:680px">
    <form method="post" action="<?= e($action) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="first_name">Nombre</label>
                <input class="input" type="text" id="first_name" name="first_name" required maxlength="80"
                       value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>">
                <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="last_name">Apellido</label>
                <input class="input" type="text" id="last_name" name="last_name" required maxlength="80"
                       value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="email">Email (usuario de acceso)</label>
                <input class="input <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email" required maxlength="150"
                       value="<?= e(old('email', $user['email'] ?? '')) ?>">
                <?php if ($m = error_for('email')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="phone">Teléfono</label>
                <input class="input" type="tel" id="phone" name="phone" maxlength="20"
                       value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="role">Rol</label>
                <select class="select" id="role" name="role" required>
                    <?php foreach ($roles as $roleValue): ?>
                        <option value="<?= e($roleValue) ?>" <?= old('role', $user['role'] ?? '') === $roleValue ? 'selected' : '' ?>>
                            <?= e(Role::label($roleValue)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($m = error_for('role')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="barber_id">Vincular a barbero <span class="muted">(rol Barbero)</span></label>
                <select class="select" id="barber_id" name="barber_id">
                    <option value="">Sin vincular</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= (int) $barber['id'] ?>" <?= (int) ($barber['user_id'] ?? 0) === (int) ($user['id'] ?? 0) && $isEdit ? 'selected' : '' ?>>
                            <?= e($barber['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="field-hint">Necesario para que vea su agenda.</div>
            </div>
        </div>

        <hr class="divider">

        <div class="grid-2">
            <div class="field">
                <label class="label" for="password"><?= $isEdit ? 'Nueva contraseña (opcional)' : 'Contraseña' ?></label>
                <input class="input <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
                       minlength="8" maxlength="100" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                <?php if ($m = error_for('password')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="password_confirmation">Repetir contraseña</label>
                <input class="input" type="password" id="password_confirmation" name="password_confirmation"
                       minlength="8" maxlength="100" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
            </div>
        </div>

        <?php if (!$isEdit): ?>
            <label class="check mb-2">
                <input type="checkbox" name="must_change_password" value="1" checked>
                <span>Exigir cambio de contraseña en el primer ingreso <span class="small muted">(recomendado)</span></span>
            </label>
        <?php endif; ?>

        <div class="row-between mt-2">
            <a href="<?= e(url('admin/usuarios')) ?>" class="btn btn-ghost btn-sm">Cancelar</a>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</div>

<?php View::stop(); ?>
