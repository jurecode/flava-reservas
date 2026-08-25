<?php
/**
 * Ruta: /app/Views/components/empty.php
 * Estado vacío: un ícono en caja, un mensaje y una acción opcional.
 *
 * @var string      $icon    nombre del ícono SVG
 * @var string      $message
 * @var string|null $hint
 * @var string|null $action  HTML del botón
 */
?>
<div class="empty">
    <span class="ico-box ico-box-lg"><?= icon($icon ?? 'info', 22) ?></span>
    <p class="bold mb-1"><?= e($message ?? 'Aún no hay registros') ?></p>
    <?php if (!empty($hint)): ?><p class="small muted"><?= e($hint) ?></p><?php endif; ?>
    <?php if (!empty($action)): ?><div class="mt-2"><?= $action ?></div><?php endif; ?>
</div>
