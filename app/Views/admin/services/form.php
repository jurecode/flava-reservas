<?php
/**
 * Ruta: /app/Views/admin/services/form.php
 */

use Core\View;

View::layout('panel');
View::start('content');

$isEdit = $service !== null;
$action = $isEdit ? url('admin/servicios/' . $service['id']) : url('admin/servicios');
?>

<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Editar servicio' : 'Nuevo servicio' ?></h1>
        <p class="sub">La duración define cuánto ocupa la agenda del barbero.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/servicios')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-once>
    <?= csrf_field() ?>

    <div class="grid-2 gap-lg">
        <div class="card">
            <h2 style="font-size:1rem">Información</h2>

            <div class="field">
                <label class="label" for="name">Nombre</label>
                <input class="input <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name" required maxlength="120"
                       value="<?= e(old('name', $service['name'] ?? '')) ?>" placeholder="Corte Fade">
                <?php if ($m = error_for('name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="category_id">Categoría</label>
                <select class="select" id="category_id" name="category_id">
                    <option value="">Sin categoría</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (int) old('category_id', $service['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="description">Descripción</label>
                <textarea class="textarea" id="description" name="description" rows="3" maxlength="1000"
                          placeholder="Degradado clásico o moderno, terminación con navaja."><?= e(old('description', $service['description'] ?? '')) ?></textarea>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="price">Precio (CLP)</label>
                    <input class="input <?= error_for('price') ? 'is-invalid' : '' ?>" type="number" id="price" name="price"
                           min="0" step="500" required value="<?= e(old('price', $service !== null ? (int) $service['price'] : '')) ?>" placeholder="15000">
                    <?php if ($m = error_for('price')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="duration_minutes">Duración (min)</label>
                    <input class="input" type="number" id="duration_minutes" name="duration_minutes"
                           min="5" max="480" step="5" required value="<?= e(old('duration_minutes', $service['duration_minutes'] ?? 45)) ?>">
                </div>
            </div>

            <?php
                $name    = 'image';
                $current = $service['image'] ?? null;
                $label   = 'Imagen del servicio';
                $hint    = 'Se muestra en la portada y en el listado. Ideal 1200×750 px.';
                $ratio   = 'wide';
                require View::path('components.image-field');
            ?>

            <div class="grid-2">
                <div class="field">
                    <label class="label" for="buffer_minutes">Colchón posterior (min)</label>
                    <input class="input" type="number" id="buffer_minutes" name="buffer_minutes" min="0" max="120" step="5"
                           value="<?= e(old('buffer_minutes', $service['buffer_minutes'] ?? 0)) ?>">
                    <div class="field-hint">Tiempo extra para limpiar o preparar.</div>
                </div>

                <div class="field">
                    <label class="label" for="color">Color</label>
                    <input class="input" type="color" id="color" name="color" style="height:48px;padding:5px"
                           value="<?= e(old('color', $service['color'] ?? '#FFC400')) ?>">
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <h2 style="font-size:1rem">Barberos que lo realizan</h2>
                <p class="small muted">Si no marcas a nadie, el servicio no aparecerá en el booking.</p>

                <div class="stack-sm mt-2" style="max-height:280px;overflow-y:auto">
                    <?php foreach ($barbers as $barber): ?>
                        <label class="check">
                            <input type="checkbox" name="barbers[]" value="<?= (int) $barber['id'] ?>"
                                   <?= in_array((int) $barber['id'], $assigned, true) ? 'checked' : '' ?>>
                            <span><?= e($barber['display_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <h2 style="font-size:1rem">Publicación</h2>

                <label class="check mb-2">
                    <input type="checkbox" name="status" value="1" <?= old('status', $service['status'] ?? 1) ? 'checked' : '' ?>>
                    <span><strong>Activo</strong></span>
                </label>

                <label class="check mb-2">
                    <input type="checkbox" name="online_bookable" value="1" <?= old('online_bookable', $service['online_bookable'] ?? 1) ? 'checked' : '' ?>>
                    <span><strong>Reservable online</strong><br><span class="small muted">Si lo desmarcas, sólo lo agenda el personal</span></span>
                </label>

                <label class="check mb-2">
                    <input type="checkbox" name="is_featured" value="1" <?= old('is_featured', $service['is_featured'] ?? 0) ? 'checked' : '' ?>>
                    <span><strong>Destacado</strong><br><span class="small muted">Aparece en la portada</span></span>
                </label>

                <div class="field">
                    <label class="label" for="sort_order">Orden</label>
                    <input class="input" type="number" id="sort_order" name="sort_order" min="0" style="max-width:110px"
                           value="<?= e(old('sort_order', $service['sort_order'] ?? 0)) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <?= $isEdit ? 'Guardar cambios' : 'Crear servicio' ?>
            </button>
        </div>
    </div>
</form>

<?php View::stop(); ?>
