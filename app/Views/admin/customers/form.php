<?php
/**
 * Ruta: /app/Views/admin/customers/form.php
 */

use App\Support\Rut;
use Core\View;

View::layout('panel');
View::start('content');

$isEdit = $customer !== null;
$action = $isEdit
    ? url(ltrim($basePath, '/') . '/clientes/' . $customer['id'])
    : url(ltrim($basePath, '/') . '/clientes');
?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar cliente' : 'Nuevo cliente' ?></h1>
        <p class="sub">Los datos se usan para el CRM y las notificaciones.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url(ltrim($basePath, '/') . '/clientes' . ($isEdit ? '/' . $customer['id'] : ''))) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="card" style="max-width:720px">
    <form method="post" action="<?= e($action) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="first_name">Nombre</label>
                <input class="input <?= error_for('first_name') ? 'is-invalid' : '' ?>" type="text" id="first_name" name="first_name"
                       value="<?= e(old('first_name', $customer['first_name'] ?? '')) ?>" required maxlength="80">
                <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="last_name">Apellido</label>
                <input class="input <?= error_for('last_name') ? 'is-invalid' : '' ?>" type="text" id="last_name" name="last_name"
                       value="<?= e(old('last_name', $customer['last_name'] ?? '')) ?>" required maxlength="80">
                <?php if ($m = error_for('last_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="rut">RUT</label>
                <input class="input <?= error_for('rut') ? 'is-invalid' : '' ?>" type="text" id="rut" name="rut" data-rut
                       value="<?= e(old('rut', $customer !== null && $customer['rut'] ? Rut::format($customer['rut']) : '')) ?>" maxlength="12" placeholder="12.345.678-9">
                <?php if ($m = error_for('rut')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="birthday">Cumpleaños</label>
                <input class="input" type="date" id="birthday" name="birthday"
                       value="<?= e(old('birthday', $customer['birthday'] ?? '')) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="phone">Teléfono</label>
                <input class="input <?= error_for('phone') ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                       value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" required maxlength="20" placeholder="+56 9 1234 5678">
                <?php if ($m = error_for('phone')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="whatsapp_phone">WhatsApp <span class="muted">(si es distinto)</span></label>
                <input class="input" type="tel" id="whatsapp_phone" name="whatsapp_phone"
                       value="<?= e(old('whatsapp_phone', $customer['whatsapp_phone'] ?? '')) ?>" maxlength="20">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="email">Email</label>
                <input class="input <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                       value="<?= e(old('email', $customer['email'] ?? '')) ?>" maxlength="150">
                <?php if ($m = error_for('email')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="preferred_barber_id">Barbero habitual</label>
                <select class="select" id="preferred_barber_id" name="preferred_barber_id">
                    <option value="">Sin preferencia</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= (int) $barber['id'] ?>"
                            <?= (int) old('preferred_barber_id', $customer['preferred_barber_id'] ?? 0) === (int) $barber['id'] ? 'selected' : '' ?>>
                            <?= e($barber['display_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field">
            <label class="label" for="notes">Notas generales</label>
            <textarea class="textarea" id="notes" name="notes" rows="3" maxlength="2000"><?= e(old('notes', $customer['notes'] ?? '')) ?></textarea>
        </div>

        <div class="row-between mt-2">
            <span class="small muted">El RUT evita fichas duplicadas.</span>
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Crear cliente' ?></button>
        </div>
    </form>
</div>

<?php View::stop(); ?>
