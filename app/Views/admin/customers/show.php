<?php
/**
 * Ruta: /app/Views/admin/customers/show.php
 * Ficha CRM completa (spec §31).
 */

use App\Support\BookingStatus;
use App\Support\Rut;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');

$name     = trim($customer['first_name'] . ' ' . $customer['last_name']);
$whatsapp = Str::whatsappLink(
    $customer['whatsapp_phone'] ?: $customer['phone'],
    "Hola {$customer['first_name']} 👋 Te escribimos de Flava Studio."
);
?>

<div class="page-head">
    <div class="row row-nowrap gap-sm">
        <span class="avatar avatar-lg"><?= e(Str::initials($customer['first_name'], $customer['last_name'])) ?></span>
        <div>
            <h1><?= e($name) ?></h1>
            <p class="sub">
                <?= $customer['rut'] ? e(Rut::format($customer['rut'])) : 'Sin RUT registrado' ?>
                <?php if ((int) $customer['no_show_count'] >= 2): ?>
                    · <span class="badge badge-noshow"><?= (int) $customer['no_show_count'] ?> inasistencias</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="page-actions">
        <?php if ($whatsapp): ?>
            <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">WhatsApp</a>
        <?php endif; ?>
        <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/nueva?customer_id=' . $customer['id'])) ?>" class="btn btn-primary btn-sm">+ Reserva</a>
        <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $customer['id'] . '/editar')) ?>" class="btn btn-ghost btn-sm">Editar</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Visitas</span>
            <span class="ico-box"><?= icon('user-check', 15) ?></span>
        </div>
        <div class="v"><?= (int) $customer['completed_bookings'] ?></div>
        <div class="d"><?= (int) $customer['total_bookings'] ?> reservas totales</div>
    </div>
    <div class="kpi kpi-ok">
        <div class="kpi-head">
            <span class="k">Total gastado</span>
            <span class="ico-box"><?= icon('trending-up', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.5rem"><?= e(money($customer['total_spent'])) ?></div>
        <div class="d">
            <?php if ((int) $customer['completed_bookings'] > 0): ?>
                Ticket: <?= e(money($customer['total_spent'] / max(1, (int) $customer['completed_bookings']))) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="kpi kpi-info">
        <div class="kpi-head">
            <span class="k">Cliente desde</span>
            <span class="ico-box"><?= icon('calendar', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.15rem">
            <?= $customer['first_visit_at'] ? e(\App\Support\DateHelper::shortEs($customer['first_visit_at'])) : '—' ?>
        </div>
        <div class="d">
            Última: <?= $customer['last_visit_at'] ? e(\App\Support\DateHelper::shortEs($customer['last_visit_at'])) : '—' ?>
        </div>
    </div>
    <div class="kpi <?= (int) $customer['no_show_count'] > 0 ? 'kpi-danger' : '' ?>">
        <div class="kpi-head">
            <span class="k">No-show</span>
            <span class="ico-box"><?= icon('ban', 15) ?></span>
        </div>
        <div class="v"><?= (int) $customer['no_show_count'] ?></div>
        <div class="d"><?= (int) $customer['cancelled_bookings'] ?> cancelaciones</div>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="stack">
        <div class="card">
            <h2 style="font-size:1rem">Datos de contacto</h2>
            <?php if (!empty($customer['phone'])): ?>
                <div class="sys-row"><span class="k">Teléfono</span><span class="v"><a href="tel:<?= e($customer['phone']) ?>"><?= e(Str::phoneDisplay($customer['phone'])) ?></a></span></div>
            <?php endif; ?>
            <?php if (!empty($customer['whatsapp_phone']) && $customer['whatsapp_phone'] !== $customer['phone']): ?>
                <div class="sys-row"><span class="k">WhatsApp</span><span class="v"><?= e(Str::phoneDisplay($customer['whatsapp_phone'])) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($customer['email'])): ?>
                <div class="sys-row"><span class="k">Email</span><span class="v"><a href="mailto:<?= e($customer['email']) ?>"><?= e($customer['email']) ?></a></span></div>
            <?php endif; ?>
            <?php if (!empty($customer['birthday'])): ?>
                <div class="sys-row"><span class="k">Cumpleaños</span><span class="v"><?= e(\App\Support\DateHelper::shortEs($customer['birthday'])) ?></span></div>
            <?php endif; ?>
            <?php if (!empty($customer['preferred_barber_name'] ?? null)): ?>
                <div class="sys-row"><span class="k">Barbero habitual</span><span class="v"><?= e($customer['preferred_barber_name']) ?></span></div>
            <?php endif; ?>
            <div class="sys-row"><span class="k">Ficha creada</span><span class="v small"><?= e($customer['created_at']) ?></span></div>
        </div>

        <?php if ($next !== null): ?>
            <div class="card card-accent">
                <div class="label">Próxima reserva</div>
                <strong><?= e(ucfirst(date_es($next['booking_date'], true))) ?> · <?= e(time_hm($next['start_time'])) ?> hrs</strong>
                <div class="small muted"><?= e($next['service_name']) ?> con <?= e($next['barber_name']) ?></div>
                <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $next['id'])) ?>" class="btn btn-xs btn-light mt-2">Ver reserva</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="font-size:1rem">Notas técnicas <span class="small muted">(las ve el barbero)</span></h2>

            <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $customer['id'] . '/nota')) ?>" class="mb-2" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="service">
                <textarea class="textarea mb-1" name="note" rows="2" required
                          placeholder="Fade bajo. Máquina #1 costados. Tijera arriba. Prefiere barba corta."></textarea>
                <div class="row-between">
                    <label class="check">
                        <input type="checkbox" name="pinned" value="1">
                        <span class="small">Fijar</span>
                    </label>
                    <button type="submit" class="btn btn-light btn-sm">Agregar</button>
                </div>
            </form>

            <?php if ($serviceNotes === []): ?>
                <p class="small muted mb-0">Sin notas todavía.</p>
            <?php else: ?>
                <div class="stack-sm">
                    <?php foreach ($serviceNotes as $note): ?>
                        <div class="tech-note">
                            <strong>
                                <?php if ($note['is_pinned']): ?><?= icon('pin', 12) ?><?php endif; ?>
                                <?= e($note['author_first'] ?? 'Equipo') ?> · <?= e(\App\Support\DateHelper::shortEs($note['created_at'])) ?>
                            </strong>
                            <?= e($note['note']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="font-size:1rem">Notas administrativas</h2>

            <form method="post" action="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $customer['id'] . '/nota')) ?>" class="mb-2" data-once>
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="admin">
                <textarea class="textarea mb-1" name="note" rows="2" required placeholder="Información interna del cliente"></textarea>
                <button type="submit" class="btn btn-light btn-sm">Agregar</button>
            </form>

            <?php if ($adminNotes === []): ?>
                <p class="small muted mb-0">Sin notas administrativas.</p>
            <?php else: ?>
                <div class="stack-sm">
                    <?php foreach ($adminNotes as $note): ?>
                        <div style="padding:9px 0;border-bottom:1px solid var(--line)">
                            <div class="tiny muted"><?= e($note['author_first'] ?? 'Equipo') ?> · <?= e($note['created_at']) ?></div>
                            <div class="small"><?= nl2br(e($note['note'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-head">
            <h2>Historial de atenciones</h2>
            <span class="small muted"><?= count($history) ?> registro(s)</span>
        </div>

        <div class="card-body stack-sm" style="max-height:760px;overflow-y:auto">
            <?php if ($history === []): ?>
                <?php $icon = 'scissors'; $message = 'Sin atenciones registradas'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($history as $booking): ?>
                    <div class="slot-row status-<?= e($booking['status']) ?>">
                        <div class="slot-time" style="flex-basis:64px">
                            <?= e(\App\Support\DateHelper::shortEs($booking['booking_date'])) ?>
                            <small><?= e(time_hm($booking['start_time'])) ?></small>
                        </div>
                        <div class="slot-info">
                            <div class="who"><?= e($booking['service_name']) ?></div>
                            <div class="what"><?= e($booking['barber_name']) ?> · <?= e($booking['public_code']) ?></div>
                        </div>
                        <div class="slot-side">
                            <span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span>
                            <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'])) ?>" class="tiny">Ver</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::stop(); ?>
