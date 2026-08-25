<?php
/**
 * Ruta: /app/Views/admin/settings/index.php
 * Configuración editable desde el panel (spec §7).
 */

use App\Support\PaymentMethod;
use Core\View;

View::layout('panel');
View::start('content');

$tabs = [
    'general' => ['Negocio', 'store'],
    'booking' => ['Reservas', 'calendar'],
    'payment' => ['Pagos', 'credit-card'],
    'notify'  => ['Notificaciones', 'bell'],
];

/** Renderiza el control adecuado según el tipo declarado en la tabla. */
$renderField = static function (array $row): void {
    $key   = $row['key_name'];
    $label = $row['label'] ?: str_replace('_', ' ', $key);
    $name  = 'settings[' . $key . ']';
    $value = $row['value'];

    if ($row['type'] === 'boolean') {
        echo '<label class="check mb-2">'
            . '<input type="checkbox" name="' . e($name) . '" value="1" ' . ((int) $value === 1 ? 'checked' : '') . '>'
            . '<span><strong>' . e($label) . '</strong>'
            . ($row['description'] ? '<br><span class="small muted">' . e($row['description']) . '</span>' : '')
            . '</span></label>';

        return;
    }

    echo '<div class="field">';
    echo '<label class="label" for="s_' . e($key) . '">' . e($label) . '</label>';

    if ($row['type'] === 'text') {
        echo '<textarea class="textarea" id="s_' . e($key) . '" name="' . e($name) . '" rows="4">' . e($value) . '</textarea>';
    } elseif ($row['type'] === 'integer') {
        echo '<input class="input" style="max-width:180px" type="number" min="0" id="s_' . e($key) . '" name="' . e($name) . '" value="' . e($value) . '">';
    } elseif ($row['type'] === 'json') {
        echo '<input class="input mono small" id="s_' . e($key) . '" name="' . e($name) . '" value="' . e($value) . '">';
    } else {
        echo '<input class="input" type="text" id="s_' . e($key) . '" name="' . e($name) . '" value="' . e($value) . '" maxlength="255">';
    }

    if ($row['description']) {
        echo '<div class="field-hint">' . e($row['description']) . '</div>';
    }

    echo '</div>';
};
?>

<div class="page-head">
    <div>
        <h1>Configuración</h1>
        <p class="sub">Estos valores cambian el comportamiento del sistema sin tocar código.</p>
    </div>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => [$label, $icon]): ?>
        <a href="<?= e(url('admin/configuracion?tab=' . $key)) ?>" class="tab <?= $tab === $key ? 'is-active' : '' ?>">
            <?= icon($icon, 15) ?> <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('admin/configuracion')) ?>" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= e($tab) ?>">

    <div class="grid-2 gap-lg">
        <div class="card">
            <h2 style="font-size:1rem"><?= e($tabs[$tab][0] ?? 'Configuración') ?></h2>

            <?php foreach ($settings[$tab] ?? [] as $row): ?>
                <?php if ($row['type'] === 'secret') continue; ?>
                <?php if ($row['key_name'] === 'payment_methods_public') continue; ?>
                <?php $renderField($row); ?>
            <?php endforeach; ?>
        </div>

        <div class="stack">
            <?php if ($tab === 'payment'): ?>
                <div class="card">
                    <h2 style="font-size:1rem">Métodos visibles en el checkout</h2>
                    <p class="small muted">Lo que el cliente puede elegir al reservar online.</p>

                    <?php $enabled = (array) setting('payment_methods_public', []); ?>
                    <div class="stack-sm mt-2">
                        <?php foreach (PaymentMethod::inStore() as $method): ?>
                            <label class="check">
                                <input type="checkbox" name="settings[payment_methods_public][]" value="<?= e($method) ?>"
                                       <?= in_array($method, $enabled, true) ? 'checked' : '' ?>>
                                <span><?= icon(PaymentMethod::icon($method), 15) ?> <?= e(PaymentMethod::label($method)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <hr class="divider">

                    <h3 style="font-size:.9rem">Pagos online</h3>
                    <p class="small muted mb-0">
                        Webpay y Mercado Pago quedan preparados en la arquitectura (<code>PaymentService</code> +
                        adaptadores) y se activan en la Etapa 2 sin modificar el resto del sistema.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'notify'): ?>
                <div class="card">
                    <h2 style="font-size:1rem">Estado de los canales</h2>
                    <div class="sys-row">
                        <span class="k">Email</span>
                        <span class="v"><?= setting('email_enabled', false) ? '<span class="dot dot-ok"></span>Activo' : '<span class="dot dot-off"></span>En cola' ?></span>
                    </div>
                    <div class="sys-row">
                        <span class="k">WhatsApp</span>
                        <span class="v"><?= setting('whatsapp_enabled', false) ? '<span class="dot dot-ok"></span>Activo' : '<span class="dot dot-off"></span>En cola' ?></span>
                    </div>
                    <p class="small muted mt-2 mb-0">
                        Las notificaciones ya se <strong>encolan</strong> desde ahora en la tabla <code>notifications</code>.
                        Al conectar el proveedor en la Etapa 2, el cron las procesa sin perder historial.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'booking'): ?>
                <div class="card card-muted">
                    <h2 style="font-size:1rem">Cómo afectan estos valores</h2>
                    <ul class="small muted" style="padding-left:18px;margin:0">
                        <li><strong>Intervalo</strong>: cada cuántos minutos empieza un horario (15 → 09:00, 09:15…).</li>
                        <li><strong>Anticipación mínima</strong>: evita reservas online para "dentro de 5 minutos".</li>
                        <li><strong>Colchón</strong>: minutos libres entre una atención y la siguiente.</li>
                        <li><strong>Confirmar automáticamente</strong>: si está apagado, las reservas quedan pendientes.</li>
                        <li>Los límites de cancelación y reprogramación aplican <strong>sólo al cliente</strong>: recepción siempre puede.</li>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'general'): ?>
                <div class="card card-muted">
                    <h2 style="font-size:1rem">Identidad</h2>
                    <p class="small muted mb-0">
                        El nombre y los datos de contacto se usan en el sitio, los comprobantes y los mensajes.
                        El dominio oficial y la zona horaria se configuran en <code>/config/app.php</code> y <code>/.env</code>.
                    </p>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-lg btn-block">Guardar configuración</button>
        </div>
    </div>
</form>

<?php View::stop(); ?>
