<?php
/**
 * Ruta: /app/Views/reception/bookings/walkin.php
 * Cliente que llega sin reserva (spec §91).
 */

use App\Support\PaymentMethod;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Walk-in</h1>
        <p class="sub">Cliente presente en el local. Queda registrado como "en espera" de inmediato.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('recepcion')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="card" style="max-width:660px">
    <form method="post" action="<?= e(url('recepcion/walk-in')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="first_name">Nombre</label>
                <input class="input" type="text" id="first_name" name="first_name" required maxlength="80" autofocus
                       value="<?= e(old('first_name')) ?>" placeholder="Juan">
                <?php if ($m = error_for('first_name')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
            </div>

            <div class="field">
                <label class="label" for="last_name">Apellido <span class="muted">(opcional)</span></label>
                <input class="input" type="text" id="last_name" name="last_name" maxlength="80" value="<?= e(old('last_name')) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="phone">Teléfono <span class="muted">(opcional)</span></label>
                <input class="input" type="tel" id="phone" name="phone" maxlength="20" value="<?= e(old('phone')) ?>">
            </div>

            <div class="field">
                <label class="label" for="rut">RUT <span class="muted">(opcional)</span></label>
                <input class="input" type="text" id="rut" name="rut" data-rut maxlength="12" value="<?= e(old('rut')) ?>">
            </div>
        </div>

        <hr class="divider">

        <div class="grid-2">
            <div class="field">
                <label class="label" for="service_id">Servicio</label>
                <select class="select" id="service_id" name="service_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>">
                            <?= e($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min · <?= e(money($service['price'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="barber_id">Barbero</label>
                <select class="select" id="barber_id" name="barber_id" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($barbers as $barber): ?>
                        <option value="<?= (int) $barber['id'] ?>"><?= e($barber['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="start_time">Hora de inicio</label>
                <input class="input" type="time" id="start_time" name="start_time" value="<?= e($now) ?>" step="300" required>
                <div class="field-hint">Se valida contra la agenda real del barbero.</div>
            </div>

            <div class="field">
                <label class="label" for="payment_method">Pago</label>
                <select class="select" id="payment_method" name="payment_method">
                    <option value="">Definir después</option>
                    <?php foreach ($methods as $method): ?>
                        <option value="<?= e($method) ?>"><?= e(PaymentMethod::label($method)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block">Registrar walk-in</button>
    </form>
</div>

<?php View::stop(); ?>
