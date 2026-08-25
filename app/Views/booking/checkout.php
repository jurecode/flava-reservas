<?php
/**
 * Ruta: /app/Views/booking/checkout.php
 * CHECKOUT — datos del cliente y confirmación (spec §11, §66).
 *
 * Dos columnas en escritorio: a la izquierda lo que hay que completar, a la
 * derecha el resumen fijo de lo que se está reservando. En móvil se apilan y el
 * resumen queda arriba, que es lo que el cliente necesita confirmar primero.
 */

use App\Support\PaymentMethod;
use Core\View;

View::setMany([
    'step'     => 4,
    'stepName' => 'Tus datos',
    'backUrl'  => url('reservar/fecha?service_id=' . (int) $service['id'] . '&barber_id=' . (int) $barber['id'] . '&date=' . $date),
    'wide'     => true,
]);
View::layout('booking');
View::start('content');
?>

<div class="booking-step-label">Último paso</div>
<h1 class="booking-h1">Confirma tu reserva</h1>

<form method="post" action="<?= e(url('reservar/confirmar')) ?>" data-checkout-form data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="service_id"   value="<?= (int) $service['id'] ?>">
    <input type="hidden" name="barber_id"    value="<?= (int) $barber['id'] ?>">
    <input type="hidden" name="booking_date" value="<?= e($date) ?>">
    <input type="hidden" name="start_time"   value="<?= e($time) ?>">

    <div class="checkout-grid">
        <div>
            <!-- 1 · Datos personales -->
            <div class="form-block">
                <div class="form-block-title">
                    <span class="step-num">1</span>
                    Tus datos
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="first_name">Nombre</label>
                        <input class="input <?= error_for('first_name') ? 'is-invalid' : '' ?>" type="text" id="first_name" name="first_name"
                               value="<?= e(old('first_name')) ?>" required autocomplete="given-name" maxlength="80" placeholder="Rodrigo">
                        <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="label" for="last_name">Apellido</label>
                        <input class="input <?= error_for('last_name') ? 'is-invalid' : '' ?>" type="text" id="last_name" name="last_name"
                               value="<?= e(old('last_name')) ?>" required autocomplete="family-name" maxlength="80" placeholder="Muñoz">
                        <?php if ($m = error_for('last_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="rut">RUT <?= $require_rut ? '' : '<span class="muted">(opcional)</span>' ?></label>
                    <input class="input <?= error_for('rut') ? 'is-invalid' : '' ?>" type="text" id="rut" name="rut" data-rut
                           value="<?= e(old('rut')) ?>" <?= $require_rut ? 'required' : '' ?>
                           inputmode="text" maxlength="12" placeholder="12.345.678-9">
                    <?php if ($m = error_for('rut')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <div class="input-group">
                            <?= icon('mail', 16) ?>
                            <input class="input <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                                   value="<?= e(old('email')) ?>" required autocomplete="email" maxlength="150" placeholder="tu@email.cl">
                        </div>
                        <div class="field-hint">Ahí te llega tu comprobante.</div>
                        <?php if ($m = error_for('email')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="label" for="phone">WhatsApp</label>
                        <div class="input-group">
                            <?= icon('phone', 16) ?>
                            <input class="input <?= error_for('phone') ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                                   value="<?= e(old('phone')) ?>" required autocomplete="tel" inputmode="tel" maxlength="20" placeholder="+56 9 1234 5678">
                        </div>
                        <?php if ($m = error_for('phone')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label class="label" for="customer_notes">Comentario <span class="muted">(opcional)</span></label>
                    <textarea class="textarea" id="customer_notes" name="customer_notes" rows="2" maxlength="500"
                              placeholder="¿Algo que debamos saber?" style="min-height:64px"><?= e(old('customer_notes')) ?></textarea>
                </div>
            </div>

            <!-- 2 · Pago -->
            <div class="form-block">
                <div class="form-block-title">
                    <span class="step-num">2</span>
                    Método de pago
                </div>

                <p class="small muted" style="margin-top:-6px">Pagas en el local al terminar tu servicio.</p>

                <div class="pay-grid">
                    <?php foreach ($methods as $index => $method): ?>
                        <label class="pay-option <?= $index === 0 ? 'is-selected' : '' ?>">
                            <input type="radio" name="payment_method" value="<?= e($method) ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                            <?= icon(PaymentMethod::icon($method), 17) ?>
                            <span><?= e(PaymentMethod::label($method)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($m = error_for('payment_method')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>
        </div>

        <!-- Resumen -->
        <aside class="checkout-aside">
            <div class="summary-card">
                <h3><?= icon('receipt', 13) ?> Tu reserva</h3>

                <div class="summary-row"><span class="k">Servicio</span><span class="v"><?= e($service['name']) ?></span></div>
                <div class="summary-row"><span class="k">Barbero</span><span class="v"><?= e($barber['display_name']) ?></span></div>
                <div class="summary-row"><span class="k">Fecha</span><span class="v"><?= e(ucfirst(date_es($date))) ?></span></div>
                <div class="summary-row"><span class="k">Hora</span><span class="v"><?= e($time) ?> – <?= e($end_time) ?></span></div>
                <div class="summary-row"><span class="k">Duración</span><span class="v"><?= (int) $duration ?> min</span></div>

                <div class="summary-total">
                    <span class="k">Total</span>
                    <span class="v"><?= e(money($price)) ?></span>
                </div>
            </div>

            <?php if ($policy): ?>
                <button type="button" class="btn btn-light btn-sm btn-block mt-2" data-modal-open="policy">
                    <?= icon('file-text', 15) ?> Ver políticas de reserva
                </button>
            <?php endif; ?>

            <label class="check mt-2">
                <input type="checkbox" name="accept_policy" value="1" required <?= old('accept_policy') ? 'checked' : '' ?>>
                <span class="small">Acepto las políticas de reserva y cancelación de Flava Studio.</span>
            </label>
            <?php if ($m = error_for('accept_policy')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>

            <button type="submit" class="btn btn-primary btn-lg btn-block mt-2">
                Confirmar reserva · <?= e(money($price)) ?>
            </button>

            <p class="center tiny muted mt-2">No necesitas crear una cuenta.</p>
        </aside>
    </div>
</form>

<?php
View::stop();

// El detalle largo de las políticas vive en un modal: no estorba el checkout.
if ($policy):
    View::start('modals');
    $id         = 'policy';
    $modalTitle = 'Políticas de reserva';
    $modalBody  = '<p class="small muted mb-0" style="white-space:pre-line">' . e($policy) . '</p>';
    $modalFoot  = '<button type="button" class="btn btn-dark btn-sm" data-modal-close>Entendido</button>';
    require View::path('components.modal');
    View::stop();
endif;
?>
