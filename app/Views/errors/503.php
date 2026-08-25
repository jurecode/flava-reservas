<?php
/**
 * Ruta: /app/Views/errors/503.php
 * Modo mantención (spec §127).
 */

use Core\View;

View::set('bodyClass', 'honeycomb');
View::layout('blank');
View::start('content');
?>
<div class="error-shell">
    <div>
        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="60" height="68" style="margin:0 auto 24px">
        <h1 style="font-size:1.9rem;margin-bottom:12px">FLAVA STUDIO</h1>
        <p style="font-size:1.06rem"><?= e($message ?? 'Estamos realizando una actualización. Volveremos en unos minutos.') ?></p>

        <?php if (!empty($minutes)): ?>
            <p class="small" style="color:rgba(255,253,245,.45)">Tiempo estimado: <?= (int) $minutes ?> minutos</p>
        <?php endif; ?>

        <div class="row gap-sm mt-3" style="justify-content:center">
            <?php if ($whatsapp = \App\Support\Str::whatsappLink(setting('business_whatsapp', ''), 'Hola, quiero reservar una hora')): ?>
                <a href="<?= e($whatsapp) ?>" class="btn btn-primary" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>
            <?php endif; ?>
            <a href="javascript:location.reload()" class="btn btn-ghost" style="color:#FFFDF5;border-color:rgba(255,255,255,.22)">Reintentar</a>
        </div>
    </div>
</div>
<?php View::stop(); ?>
