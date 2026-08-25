<?php
/**
 * Ruta: /app/Views/superadmin/system/logs.php
 * Logs técnicos. Los secretos ya se enmascaran al escribirse (spec §143).
 */

use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Logs técnicos</h1>
        <p class="sub">Últimas 400 líneas de <code><?= e($selected ?: 'sin archivo') ?></code></p>
    </div>
    <div class="page-actions">
        <form method="get" class="row gap-sm">
            <select class="select" name="file" data-auto-submit style="padding:8px 32px 8px 12px;font-size:.86rem">
                <?php foreach ($files as $file): ?>
                    <option value="<?= e($file) ?>" <?= $selected === $file ? 'selected' : '' ?>><?= e($file) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($files === []): ?>
    <div class="card">
        <?php $icon = 'file-text'; $message = 'Todavía no hay archivos de log'; require View::path('components.empty'); ?>
    </div>
<?php else: ?>
    <div class="log-view"><?= e($content ?: '(archivo vacío)') ?></div>

    <p class="small muted mt-2">
        Los tokens y cabeceras de autorización se enmascaran automáticamente antes de escribirse.
        Los errores SQL nunca se muestran al usuario final: sólo quedan aquí.
    </p>
<?php endif; ?>

<?php View::stop(); ?>
