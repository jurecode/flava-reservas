<?php
/**
 * Ruta: /app/Views/admin/barbers/form.php
 */

use Core\View;

View::layout('panel');
View::start('content');

$isEdit = $barber !== null;
$action = $isEdit ? url('admin/barberos/' . $barber['id']) : url('admin/barberos');
?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar barbero' : 'Nuevo barbero' ?></h1>
        <p class="sub"><?= $isEdit ? e($barber['display_name']) : 'Ficha, servicios y acceso al panel' ?></p>
    </div>
    <div class="page-actions">
        <?php if ($isEdit): ?>
            <a href="<?= e(url('admin/barberos/' . $barber['id'] . '/horario')) ?>" class="btn btn-ghost btn-sm">Editar horario</a>
        <?php endif; ?>
        <a href="<?= e(url('admin/barberos')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>

    <div class="grid-2 gap-lg">
        <div class="stack">
            <div class="card">
                <h2 style="font-size:1rem">Datos personales</h2>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="first_name">Nombre</label>
                        <input class="input" type="text" id="first_name" name="first_name" required maxlength="80"
                               value="<?= e(old('first_name', $barber['first_name'] ?? '')) ?>">
                        <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="label" for="last_name">Apellido</label>
                        <input class="input" type="text" id="last_name" name="last_name" maxlength="80"
                               value="<?= e(old('last_name', $barber['last_name'] ?? '')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="display_name">Nombre visible en el booking</label>
                    <input class="input" type="text" id="display_name" name="display_name" required maxlength="80"
                           value="<?= e(old('display_name', $barber['display_name'] ?? '')) ?>" placeholder="Sebastián">
                    <div class="field-hint">Es el nombre que ve el cliente al reservar.</div>
                </div>

                <?php
                    $name    = 'photo';
                    $current = $barber['photo'] ?? null;
                    $label   = 'Foto del barbero';
                    $hint    = 'Se muestra en el sitio y en el booking. Ideal 800×1000 px (vertical).';
                    $ratio   = 'tall';
                    require View::path('components.image-field');
                ?>

                <div class="field">
                    <label class="label" for="specialty">Especialidad</label>
                    <input class="input" type="text" id="specialty" name="specialty" maxlength="160"
                           value="<?= e(old('specialty', $barber['specialty'] ?? '')) ?>"
                           placeholder="Fade · Barbería clásica · Barba">
                </div>

                <div class="field">
                    <label class="label" for="bio">Presentación</label>
                    <textarea class="textarea" id="bio" name="bio" rows="3" maxlength="1000"><?= e(old('bio', $barber['bio'] ?? '')) ?></textarea>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" maxlength="150"
                               value="<?= e(old('email', $barber['email'] ?? '')) ?>">
                    </div>

                    <div class="field">
                        <label class="label" for="phone">Teléfono</label>
                        <input class="input" type="tel" id="phone" name="phone" maxlength="20"
                               value="<?= e(old('phone', $barber['phone'] ?? '')) ?>">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="instagram">Instagram</label>
                        <input class="input" type="text" id="instagram" name="instagram" maxlength="120"
                               value="<?= e(old('instagram', $barber['instagram'] ?? '')) ?>" placeholder="@usuario">
                    </div>

                    <div class="field">
                        <label class="label" for="color">Color en el calendario</label>
                        <input class="input" type="color" id="color" name="color" style="height:48px;padding:5px"
                               value="<?= e(old('color', $barber['color'] ?? '#FFC400')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h2 style="font-size:1rem">Servicios que realiza</h2>
                <p class="small muted">Sólo aparecerá en el booking de los servicios marcados.</p>

                <div class="stack-sm mt-2" style="max-height:320px;overflow-y:auto">
                    <?php foreach ($services as $service): ?>
                        <label class="check">
                            <input type="checkbox" name="services[]" value="<?= (int) $service['id'] ?>"
                                   <?= in_array((int) $service['id'], $assigned, true) ? 'checked' : '' ?>>
                            <span>
                                <?= e($service['name']) ?>
                                <span class="small muted">· <?= (int) $service['duration_minutes'] ?> min · <?= e(money($service['price'])) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2 style="font-size:1rem">Visibilidad</h2>

                <label class="check mb-2">
                    <input type="checkbox" name="status" value="1" <?= old('status', $barber['status'] ?? 1) ? 'checked' : '' ?>>
                    <span><strong>Activo</strong><br><span class="small muted">Puede recibir reservas</span></span>
                </label>

                <label class="check">
                    <input type="checkbox" name="accepts_online" value="1" <?= old('accepts_online', $barber['accepts_online'] ?? 1) ? 'checked' : '' ?>>
                    <span><strong>Visible en el booking online</strong><br><span class="small muted">Si lo desmarcas, sólo recepción podrá agendarle</span></span>
                </label>

                <div class="field mt-2">
                    <label class="label" for="sort_order">Orden en el listado</label>
                    <input class="input" type="number" id="sort_order" name="sort_order" min="0" style="max-width:110px"
                           value="<?= e(old('sort_order', $barber['sort_order'] ?? 0)) ?>">
                </div>
            </div>

            <?php if (!$isEdit): ?>
                <div class="card">
                    <h2 style="font-size:1rem">Acceso al panel</h2>
                    <label class="check mb-2">
                        <input type="checkbox" name="create_user" value="1" data-toggle-user>
                        <span><strong>Crear cuenta de acceso</strong><br><span class="small muted">Podrá ver su agenda en /barbero</span></span>
                    </label>

                    <div class="field">
                        <label class="label" for="password">Contraseña temporal</label>
                        <input class="input" type="text" id="password" name="password" minlength="8" maxlength="100"
                               placeholder="Se genera automáticamente si lo dejas vacío">
                        <div class="field-hint">Se le pedirá cambiarla en el primer ingreso.</div>
                    </div>
                </div>
            <?php elseif (!empty($user)): ?>
                <div class="card">
                    <h2 style="font-size:1rem">Acceso al panel</h2>
                    <div class="sys-row"><span class="k">Cuenta</span><span class="v"><?= e($user['email']) ?></span></div>
                    <div class="sys-row"><span class="k">Estado</span><span class="v"><?= (int) $user['status'] === 1 ? 'Activa' : 'Desactivada' ?></span></div>
                    <a href="<?= e(url('admin/usuarios/' . $user['id'] . '/editar')) ?>" class="btn btn-ghost btn-sm mt-2">Administrar cuenta</a>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <?= $isEdit ? 'Guardar cambios' : 'Crear barbero' ?>
            </button>
        </div>
    </div>
</form>

<?php View::stop(); ?>
