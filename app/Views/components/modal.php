<?php
/**
 * Ruta: /app/Views/components/modal.php
 * Modal reutilizable. Se abre con [data-modal-open="id"] y se cierra con
 * [data-modal-close], la tecla Escape o un clic en el fondo (ver panel.js).
 *
 * @var string      $id
 * @var string      $modalTitle
 * @var string      $modalBody   HTML del contenido
 * @var string|null $modalFoot   HTML de los botones
 * @var string|null $modalSize   'lg' para ancho mayor
 */
?>
<div class="modal-backdrop hidden" data-modal="<?= e($id) ?>" role="dialog" aria-modal="true" aria-labelledby="modal-title-<?= e($id) ?>">
    <div class="modal <?= ($modalSize ?? '') === 'lg' ? 'modal-lg' : '' ?>">
        <div class="modal-head">
            <h3 id="modal-title-<?= e($id) ?>"><?= e($modalTitle) ?></h3>
            <button type="button" class="modal-close" data-modal-close aria-label="Cerrar"><?= icon('close', 18) ?></button>
        </div>

        <div class="modal-body"><?= $modalBody ?></div>

        <?php if (!empty($modalFoot)): ?>
            <div class="modal-foot"><?= $modalFoot ?></div>
        <?php endif; ?>
    </div>
</div>
