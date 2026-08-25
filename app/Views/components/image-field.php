<?php
/**
 * Ruta: /app/Views/components/image-field.php
 * Campo de imagen con vista previa y opción de quitar.
 * El formulario que lo incluya debe llevar enctype="multipart/form-data".
 *
 * @var string      $name     nombre del input
 * @var string|null $current  ruta relativa guardada
 * @var string      $label
 * @var string|null $hint
 * @var string|null $ratio    'wide' (16/10) o 'tall' (4/5)
 */

use App\Services\UploadService;

$current = $current ?? null;
$ratio   = $ratio ?? 'wide';
$photo   = upload_url($current);
?>
<div class="field image-field" data-image-field>
    <label class="label" for="<?= e($name) ?>"><?= e($label) ?></label>

    <div class="image-preview image-preview-<?= e($ratio) ?>" data-image-preview>
        <?php if ($photo !== null): ?>
            <img src="<?= e($photo) ?>" alt="" data-image-thumb>
        <?php else: ?>
            <div class="image-placeholder" data-image-thumb-empty>
                <?= icon('image', 22) ?>
                <span>Sin imagen</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="row gap-sm mt-1">
        <label class="btn btn-light btn-sm" style="cursor:pointer">
            <?= icon('upload', 15) ?> <?= $photo !== null ? 'Cambiar' : 'Subir imagen' ?>
            <input type="file" id="<?= e($name) ?>" name="<?= e($name) ?>"
                   accept="image/jpeg,image/png,image/webp" hidden data-image-input>
        </label>

        <?php if ($photo !== null): ?>
            <label class="check">
                <input type="checkbox" name="<?= e($name) ?>_remove" value="1">
                <span class="small">Quitar imagen</span>
            </label>
        <?php endif; ?>
    </div>

    <div class="field-hint">
        <?= e($hint ?? 'JPG, PNG o WebP · hasta 4 MB.') ?>
        <?php if (!UploadService::canResize()): ?>
            <br><strong>Este servidor no puede redimensionar</strong>: sube la imagen ya recortada.
        <?php endif; ?>
    </div>
</div>
