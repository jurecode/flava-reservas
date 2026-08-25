<?php
/**
 * Ruta: /app/Views/admin/bookings/create.php
 * Creación manual de reservas (teléfono, WhatsApp, presencial — spec §36).
 * Usa el MISMO motor de disponibilidad que el booking público.
 */

use App\Support\BookingSource;
use App\Support\PaymentMethod;
use App\Support\Rut;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Nueva reserva</h1>
        <p class="sub">Busca al cliente o créalo en el mismo formulario.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url(ltrim($basePath, '/') . '/reservas')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<form method="post" action="<?= e(url(ltrim($basePath, '/') . '/reservas')) ?>" data-booking-form data-once>
    <?= csrf_field() ?>

    <div class="grid-2 gap-lg">
        <div class="stack">
            <!-- 1. Cliente -->
            <div class="card">
                <h2 style="font-size:1rem">1 · Cliente</h2>

                <div class="field search-box">
                    <label class="label" for="customerSearch">Buscar cliente</label>
                    <input class="input" type="search" id="customerSearch" data-customer-search
                           placeholder="Nombre, RUT o teléfono" autocomplete="off"
                           <?= $customer !== null ? 'value="' . e(trim($customer['first_name'] . ' ' . $customer['last_name'])) . '"' : '' ?>>
                    <div class="search-results" data-customer-results></div>
                    <div class="field-hint">Escribe al menos 2 caracteres.</div>
                </div>

                <input type="hidden" name="customer_id" data-customer-id value="<?= $customer !== null ? (int) $customer['id'] : '' ?>">

                <div class="card card-accent mb-2 <?= $customer === null ? 'hidden' : '' ?>" data-customer-card>
                    <div class="row-between">
                        <div>
                            <strong data-customer-name><?= $customer !== null ? e(trim($customer['first_name'] . ' ' . $customer['last_name'])) : '' ?></strong>
                            <div class="small muted" data-customer-detail>
                                <?php if ($customer !== null): ?>
                                    <?= e($customer['rut'] ? Rut::format($customer['rut']) : '') ?>
                                    <?= e($customer['phone'] ? ' · ' . Str::phoneDisplay($customer['phone']) : '') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="btn btn-xs btn-ghost" data-customer-clear>Cambiar</button>
                    </div>
                </div>

                <div data-new-customer class="<?= $customer !== null ? 'hidden' : '' ?>">
                    <div class="divider-text">o crea uno nuevo</div>

                    <div class="grid-2">
                        <div class="field">
                            <label class="label" for="first_name">Nombre</label>
                            <input class="input" type="text" id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" maxlength="80">
                        </div>
                        <div class="field">
                            <label class="label" for="last_name">Apellido</label>
                            <input class="input" type="text" id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" maxlength="80">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="field">
                            <label class="label" for="phone">Teléfono</label>
                            <input class="input" type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" maxlength="20" placeholder="+56 9 1234 5678">
                        </div>
                        <div class="field">
                            <label class="label" for="rut">RUT <span class="muted">(opcional)</span></label>
                            <input class="input" type="text" id="rut" name="rut" data-rut value="<?= e(old('rut')) ?>" maxlength="12">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="email">Email <span class="muted">(opcional)</span></label>
                        <input class="input" type="email" id="email" name="email" value="<?= e(old('email')) ?>" maxlength="150">
                    </div>
                </div>
            </div>

            <!-- 2. Servicio y barbero -->
            <div class="card">
                <h2 style="font-size:1rem">2 · Servicio y barbero</h2>

                <div class="field">
                    <label class="label" for="service_id">Servicio</label>
                    <select class="select" id="service_id" name="service_id" data-service required>
                        <option value="">Selecciona un servicio</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int) $service['id'] ?>" data-duration="<?= (int) $service['duration_minutes'] ?>">
                                <?= e($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min · <?= e(money($service['price'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="label" for="barber_id">Barbero</label>
                    <select class="select" id="barber_id" name="barber_id" data-barber required>
                        <option value="">Selecciona un barbero</option>
                        <?php foreach ($barbers as $barber): ?>
                            <option value="<?= (int) $barber['id'] ?>"><?= e($barber['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint" data-barber-hint></div>
                </div>
            </div>
        </div>

        <div class="stack">
            <!-- 3. Fecha y hora -->
            <div class="card">
                <h2 style="font-size:1rem">3 · Fecha y hora</h2>

                <div class="field">
                    <label class="label" for="booking_date">Fecha</label>
                    <input class="input" type="date" id="booking_date" name="booking_date" data-date
                           value="<?= e(old('booking_date', today())) ?>" min="<?= e(today()) ?>" required>
                </div>

                <div class="field">
                    <label class="label">Horarios disponibles</label>
                    <div data-slots class="small muted">Elige servicio, barbero y fecha.</div>
                    <input type="hidden" name="start_time" data-time value="">
                </div>
            </div>

            <!-- 4. Detalles -->
            <div class="card">
                <h2 style="font-size:1rem">4 · Detalles</h2>

                <div class="grid-2">
                    <div class="field">
                        <label class="label" for="source">Origen</label>
                        <select class="select" id="source" name="source" required>
                            <?php foreach ($sources as $source): ?>
                                <option value="<?= e($source) ?>" <?= $source === BookingSource::RECEPTION ? 'selected' : '' ?>>
                                    <?= e(BookingSource::label($source)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label class="label" for="payment_method">Pago previsto</label>
                        <select class="select" id="payment_method" name="payment_method">
                            <option value="">Definir después</option>
                            <?php foreach ($methods as $method): ?>
                                <option value="<?= e($method) ?>"><?= e(PaymentMethod::label($method)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="internal_notes">Nota interna</label>
                    <textarea class="textarea" id="internal_notes" name="internal_notes" rows="2" maxlength="1000"
                              placeholder="Sólo visible para el equipo"><?= e(old('internal_notes')) ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" data-submit disabled>Crear reserva</button>
        </div>
    </div>
</form>

<?php View::stop(); ?>
