<?php
/**
 * Ruta: /app/Views/install/business.php
 * PASO 5 — Datos de la barbería.
 */

use Core\View;

View::layout('install');
View::start('content');
?>

<div class="install-head">
    <h1>Datos de tu barbería</h1>
    <p>Aparecen en el sitio, en los comprobantes y en los mensajes al cliente. Puedes cambiarlos después.</p>
</div>

<div class="card">
    <form method="post" action="<?= e(url('instalar/negocio')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="field">
            <label class="label" for="name">Nombre del negocio</label>
            <input class="input <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                   required maxlength="120" autofocus value="<?= e(old('name', 'Flava Studio')) ?>">
            <?php if ($m = error_for('name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
        </div>

        <div class="field">
            <label class="label" for="address">Dirección</label>
            <div class="input-group">
                <?= icon('map-pin', 16) ?>
                <input class="input" type="text" id="address" name="address" maxlength="255"
                       placeholder="Av. Principal 1234, Santiago" value="<?= e(old('address')) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="phone">Teléfono</label>
                <div class="input-group">
                    <?= icon('phone', 16) ?>
                    <input class="input" type="tel" id="phone" name="phone" maxlength="20"
                           placeholder="+56 9 1234 5678" value="<?= e(old('phone')) ?>">
                </div>
            </div>

            <div class="field">
                <label class="label" for="whatsapp">WhatsApp <span class="muted">(si es distinto)</span></label>
                <div class="input-group">
                    <?= icon('whatsapp', 16) ?>
                    <input class="input" type="tel" id="whatsapp" name="whatsapp" maxlength="20"
                           value="<?= e(old('whatsapp')) ?>">
                </div>
            </div>
        </div>

        <div class="field">
            <label class="label" for="email">Email de contacto</label>
            <div class="input-group">
                <?= icon('mail', 16) ?>
                <input class="input" type="email" id="email" name="email" maxlength="150"
                       placeholder="hola@tudominio.cl" value="<?= e(old('email')) ?>">
            </div>
        </div>

        <hr class="divider">

        <div class="field">
            <label class="label" for="app_url">Dirección pública del sitio</label>
            <div class="input-group">
                <?= icon('globe', 16) ?>
                <input class="input <?= error_for('app_url') ? 'is-invalid' : '' ?>" type="url" id="app_url" name="app_url"
                       maxlength="200" value="<?= e(old('app_url', $suggestUrl)) ?>">
            </div>
            <div class="field-hint">
                Se usa en los enlaces de las reservas y los correos. Detectamos
                <code><?= e($suggestUrl) ?></code>; corrígelo si tu dominio definitivo es otro.
            </div>
            <?php if ($m = error_for('app_url')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            Guardar y terminar <?= icon('arrow-right', 16) ?>
        </button>
    </form>
</div>

<div class="install-nav">
    <a href="<?= e(url('instalar/administrador')) ?>" class="btn btn-ghost"><?= icon('arrow-left', 15) ?> Atrás</a>
</div>

<?php View::stop(); ?>
