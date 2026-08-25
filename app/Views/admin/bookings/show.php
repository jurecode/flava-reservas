<?php
/**
 * Ruta: /app/Views/admin/bookings/show.php
 * Detalle y gestión completa de una reserva.
 */

use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\PaymentMethod;
use App\Support\PaymentStatus;
use App\Support\Rut;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');

$customerName = trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name']);
$isClosed     = in_array($booking['status'], [BookingStatus::COMPLETED, BookingStatus::CANCELLED], true);
$whatsapp     = Str::whatsappLink(
    $booking['customer_whatsapp'] ?: $booking['customer_phone'],
    "Hola {$booking['customer_first_name']} 👋 Te escribimos de Flava Studio por tu reserva {$booking['public_code']}."
);
?>

<div class="page-head">
    <div>
        <div class="row gap-sm mb-1">
            <span class="mono bold"><?= e($booking['public_code']) ?></span>
            <span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span>
            <span class="badge <?= e(PaymentStatus::badgeClass($booking['payment_status'])) ?>"><?= e(PaymentStatus::label($booking['payment_status'])) ?></span>
        </div>
        <h1><?= e($customerName) ?></h1>
        <p class="sub">
            <?= e($booking['service_name']) ?> · <?= e($booking['barber_name']) ?> ·
            <?= e(ucfirst(date_es($booking['booking_date'], true))) ?> a las <?= e(time_hm($booking['start_time'])) ?> hrs
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url(ltrim($basePath, '/') . '/reservas')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
        <?php if ($whatsapp): ?>
            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">WhatsApp</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($nextStates !== []): ?>
    <div class="card mb-3">
        <div class="label mb-1">Cambiar estado</div>
        <?php
            $states = $nextStates;
            $action = url(ltrim($basePath, '/') . '/reservas/' . $booking['id'] . '/estado');
            require View::path('components.status-form');
        ?>
    </div>
<?php endif; ?>

<div class="grid-2 gap-lg">
    <div class="stack">
        <!-- Cliente -->
        <div class="card">
            <div class="row-between mb-2">
                <h2 style="font-size:1rem;margin:0">Cliente</h2>
                <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $booking['customer_id'])) ?>" class="btn btn-xs btn-ghost">Ver ficha</a>
            </div>

            <div class="sys-row"><span class="k">Nombre</span><span class="v"><?= e($customerName) ?></span></div>
            <?php if (!empty($booking['customer_rut'])): ?>
                <div class="sys-row"><span class="k">RUT</span><span class="v mono"><?= e(Rut::format($booking['customer_rut'])) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($booking['customer_phone'])): ?>
                <div class="sys-row"><span class="k">Teléfono</span><span class="v"><a href="tel:<?= e($booking['customer_phone']) ?>"><?= e(Str::phoneDisplay($booking['customer_phone'])) ?></a></span></div>
            <?php endif; ?>
            <?php if (!empty($booking['customer_email'])): ?>
                <div class="sys-row"><span class="k">Email</span><span class="v"><a href="mailto:<?= e($booking['customer_email']) ?>"><?= e($booking['customer_email']) ?></a></span></div>
            <?php endif; ?>
            <div class="sys-row"><span class="k">Visitas</span><span class="v"><?= (int) $booking['customer_visits'] ?></span></div>
            <?php if ((int) $booking['customer_no_shows'] > 0): ?>
                <div class="sys-row"><span class="k">No-show</span><span class="v" style="color:var(--danger)"><?= (int) $booking['customer_no_shows'] ?></span></div>
            <?php endif; ?>
        </div>

        <!-- Detalle -->
        <div class="card">
            <h2 style="font-size:1rem">Detalle de la reserva</h2>
            <div class="sys-row"><span class="k">Servicio</span><span class="v"><?= e($booking['service_name']) ?></span></div>
            <div class="sys-row"><span class="k">Barbero</span><span class="v"><?= e($booking['barber_name']) ?></span></div>
            <div class="sys-row"><span class="k">Horario</span><span class="v"><?= e(time_hm($booking['start_time'])) ?> – <?= e(time_hm($booking['end_time'])) ?> (<?= (int) $booking['duration_minutes'] ?> min)</span></div>
            <div class="sys-row"><span class="k">Origen</span><span class="v"><?= e(BookingSource::label($booking['source'])) ?></span></div>
            <?php if (!empty($booking['created_by_name'])): ?>
                <div class="sys-row"><span class="k">Creada por</span><span class="v"><?= e($booking['created_by_name']) ?></span></div>
            <?php endif; ?>
            <div class="sys-row"><span class="k">Subtotal</span><span class="v"><?= e(money($booking['subtotal'])) ?></span></div>
            <?php if ((float) $booking['discount'] > 0): ?>
                <div class="sys-row"><span class="k">Descuento</span><span class="v">− <?= e(money($booking['discount'])) ?></span></div>
            <?php endif; ?>
            <div class="sys-row"><span class="k bold">Total</span><span class="v" style="font-size:1.1rem"><?= e(money($booking['total'])) ?></span></div>

            <?php if (!empty($booking['customer_notes'])): ?>
                <div class="mt-2">
                    <div class="label">Comentario del cliente</div>
                    <p class="small mb-0"><?= nl2br(e($booking['customer_notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagos -->
        <div class="card">
            <div class="row-between mb-2">
                <h2 style="font-size:1rem;margin:0">Pagos</h2>
                <?php if ($balance > 0): ?>
                    <span class="badge badge-pending">Saldo <?= e(money($balance)) ?></span>
                <?php else: ?>
                    <span class="badge badge-paid">Sin saldo</span>
                <?php endif; ?>
            </div>

            <?php if ($payments !== []): ?>
                <div class="stack-sm mb-2">
                    <?php foreach ($payments as $payment): ?>
                        <div class="row-between small" style="padding:7px 0;border-bottom:1px solid var(--line)">
                            <span>
                                <?= icon(PaymentMethod::icon($payment['payment_method']), 14) ?>
                                <?= e(PaymentMethod::label($payment['payment_method'])) ?>
                                <span class="muted">· <?= e($payment['paid_at'] ?: $payment['created_at']) ?></span>
                            </span>
                            <span class="bold"><?= e(money($payment['amount'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($balance > 0 && $booking['status'] !== BookingStatus::CANCELLED): ?>
                <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'] . '/pago')) ?>" data-once>
                    <?= csrf_field() ?>
                    <div class="row gap-sm">
                        <div class="field grow" style="margin:0;min-width:110px">
                            <label class="label" for="amount">Monto</label>
                            <input class="input" type="number" id="amount" name="amount" min="1" step="1"
                                   value="<?= (int) $balance ?>" required>
                        </div>
                        <div class="field grow" style="margin:0;min-width:130px">
                            <label class="label" for="payment_method">Método</label>
                            <select class="select" id="payment_method" name="payment_method" required>
                                <?php foreach ($methods as $method): ?>
                                    <option value="<?= e($method) ?>" <?= $booking['payment_method'] === $method ? 'selected' : '' ?>>
                                        <?= e(PaymentMethod::label($method)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success btn-sm btn-block mt-2">Registrar pago</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="stack">
        <!-- Reprogramar -->
        <?php if (!$isClosed): ?>
            <div class="card">
                <h2 style="font-size:1rem">Reprogramar</h2>
                <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'] . '/reprogramar')) ?>"
                      data-reschedule data-booking="<?= (int) $booking['id'] ?>"
                      data-service="<?= (int) $booking['service_id'] ?>" data-once>
                    <?= csrf_field() ?>

                    <div class="grid-2">
                        <div class="field">
                            <label class="label" for="rs_barber">Barbero</label>
                            <select class="select" id="rs_barber" name="barber_id" data-rs-barber>
                                <?php foreach ($barbers as $barber): ?>
                                    <option value="<?= (int) $barber['id'] ?>" <?= (int) $barber['id'] === (int) $booking['barber_id'] ? 'selected' : '' ?>>
                                        <?= e($barber['display_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label class="label" for="rs_date">Fecha</label>
                            <input class="input" type="date" id="rs_date" name="booking_date" data-rs-date
                                   value="<?= e($booking['booking_date']) ?>" min="<?= e(today()) ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label">Horarios disponibles</label>
                        <div data-rs-slots class="small muted">Selecciona un barbero y una fecha.</div>
                        <input type="hidden" name="start_time" data-rs-time value="">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm btn-block" data-rs-submit disabled>Reprogramar</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Notas internas -->
        <div class="card">
            <h2 style="font-size:1rem">Notas</h2>

            <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'])) ?>" data-once>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="label" for="internal_notes">Nota interna de la reserva</label>
                    <textarea class="textarea" id="internal_notes" name="internal_notes" rows="3"
                              placeholder="Sólo visible para el equipo"><?= e($booking['internal_notes']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-light btn-sm">Guardar nota</button>
            </form>

            <hr class="divider">

            <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'] . '/nota')) ?>" data-once>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="label" for="note">Nota técnica del cliente</label>
                    <textarea class="textarea" id="note" name="note" rows="2"
                              placeholder="Fade bajo. Máquina #1 costados. Tijera arriba."></textarea>
                    <div class="field-hint">La verá el barbero en su agenda.</div>
                </div>
                <input type="hidden" name="type" value="service">
                <label class="check mb-2">
                    <input type="checkbox" name="pinned" value="1">
                    <span class="small">Fijar: mostrarla siempre</span>
                </label>
                <button type="submit" class="btn btn-light btn-sm">Agregar nota</button>
            </form>
        </div>

        <!-- Historial -->
        <div class="card">
            <h2 style="font-size:1rem">Historial</h2>
            <div class="timeline mt-2">
                <?php foreach ($history as $entry): ?>
                    <div class="timeline-item">
                        <div class="when"><?= e($entry['created_at']) ?></div>
                        <div class="what">
                            <?= $entry['old_status'] ? e(BookingStatus::label($entry['old_status'])) . ' → ' : '' ?>
                            <?= e(BookingStatus::label($entry['new_status'])) ?>
                        </div>
                        <?php if (!empty($entry['note'])): ?>
                            <div class="small muted"><?= e($entry['note']) ?></div>
                        <?php endif; ?>
                        <div class="tiny muted">
                            <?= $entry['first_name'] ? e(trim($entry['first_name'] . ' ' . $entry['last_name'])) : 'Cliente / sistema' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cancelar -->
        <?php if (BookingStatus::isCancellable((string) $booking['status'])): ?>
            <div class="card card-muted">
                <div class="row-between">
                    <div>
                        <strong class="small">Cancelar reserva</strong>
                        <div class="small muted">El horario queda disponible de inmediato.</div>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" data-modal-open="cancel-booking">
                        <?= icon('x-circle', 15) ?> Cancelar
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
View::stop();

// La cancelación vive en un modal: exige una confirmación consciente.
if (BookingStatus::isCancellable((string) $booking['status'])):
    View::start('modals');
    $id         = 'cancel-booking';
    $modalTitle = 'Cancelar reserva ' . $booking['public_code'];
    $modalBody  = '<p class="small">Se cancelará la reserva de <strong>' . e($customerName) . '</strong> del '
        . e(date_es($booking['booking_date'], true)) . ' a las ' . e(time_hm($booking['start_time'])) . ' hrs.</p>'
        . '<p class="small muted">El horario vuelve a quedar disponible y el cliente recibirá el aviso correspondiente.</p>'
        . '<div class="field mb-0"><label class="label" for="cancel_reason">Motivo (opcional)</label>'
        . '<input class="input" type="text" id="cancel_reason" name="reason" form="cancelBookingForm" maxlength="255" placeholder="Ej.: el cliente reagendó por teléfono"></div>';
    $modalFoot  = '<button type="button" class="btn btn-ghost btn-sm" data-modal-close>Volver</button>'
        . '<form method="post" id="cancelBookingForm" action="'
        . e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'] . '/cancelar')) . '">'
        . csrf_field()
        . '<button type="submit" class="btn btn-danger btn-sm">Sí, cancelar reserva</button></form>';
    require View::path('components.modal');
    View::stop();
endif;
?>
