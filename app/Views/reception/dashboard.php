<?php
/**
 * Ruta: /app/Views/reception/dashboard.php
 * Panel operativo de recepción (spec §35).
 */

use App\Support\BookingStatus;
use Core\View;

View::layout('panel');
View::start('content');

$basePath = '/recepcion';
$now      = now()->format('H:i');
$upcoming = array_filter($bookings, static fn (array $b): bool => time_hm($b['start_time']) >= $now && in_array($b['status'], BookingStatus::active(), true));
?>

<div class="page-head">
    <div>
        <h1>Hoy en Flava</h1>
        <p class="sub"><?= e(ucfirst(date_es($date, true))) ?> · <?= count($barbers) ?> barbero(s) en turno</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('recepcion/reservas/nueva')) ?>" class="btn btn-primary btn-sm">+ Nueva reserva</a>
        <a href="<?= e(url('recepcion/walk-in')) ?>" class="btn btn-dark btn-sm"><?= icon('walk', 15) ?> Walk-in</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Reservas hoy</span>
            <span class="ico-box"><?= icon('calendar', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['total'] ?></div>
        <div class="d"><?= (int) $stats['pending'] ?> por llegar</div>
    </div>
    <div class="kpi kpi-ok">
        <div class="kpi-head">
            <span class="k">En espera</span>
            <span class="ico-box"><?= icon('user-check', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['waiting'] ?></div>
        <div class="d">clientes que ya llegaron</div>
    </div>
    <div class="kpi kpi-info">
        <div class="kpi-head">
            <span class="k">En atención</span>
            <span class="ico-box"><?= icon('scissors', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['in_progress'] ?></div>
        <div class="d"><?= (int) $stats['completed'] ?> finalizadas</div>
    </div>
    <div class="kpi <?= (int) $stats['unpaid'] > 0 ? 'kpi-danger' : '' ?>">
        <div class="kpi-head">
            <span class="k">Sin cobrar</span>
            <span class="ico-box"><?= icon('credit-card', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['unpaid'] ?></div>
        <div class="d">atenciones finalizadas</div>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card card-flush">
        <div class="card-head">
            <h2>Próximas de hoy</h2>
            <a href="<?= e(url('recepcion/agenda')) ?>" class="btn btn-xs btn-ghost">Ver agenda completa</a>
        </div>

        <div class="card-body stack-sm" style="max-height:560px;overflow-y:auto">
            <?php if ($upcoming === []): ?>
                <?php $icon = 'check-circle'; $message = 'No quedan reservas por atender hoy'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($upcoming as $booking): ?>
                    <?php require View::path('components.booking-row'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <h2 style="font-size:1rem">Acciones rápidas</h2>
            <div class="stack-sm mt-2">
                <a href="<?= e(url('recepcion/reservas/nueva')) ?>" class="btn btn-primary btn-block">+ Nueva reserva</a>
                <a href="<?= e(url('recepcion/clientes')) ?>" class="btn btn-light btn-block"><?= icon('search', 15) ?> Buscar cliente</a>
                <a href="<?= e(url('recepcion/agenda')) ?>" class="btn btn-light btn-block"><?= icon('list', 15) ?> Ver agenda</a>
                <a href="<?= e(url('recepcion/bloqueos')) ?>" class="btn btn-light btn-block"><?= icon('ban', 15) ?> Bloquear horario</a>
            </div>
        </div>

        <div class="card card-flush">
            <div class="card-head"><h2>Todo el día</h2></div>
            <div class="card-body stack-sm" style="max-height:420px;overflow-y:auto">
                <?php if ($bookings === []): ?>
                    <p class="small muted mb-0">Sin reservas registradas para hoy.</p>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <div class="row-between small" style="padding:7px 0;border-bottom:1px solid var(--line)">
                            <span>
                                <strong><?= e(time_hm($booking['start_time'])) ?></strong>
                                <a href="<?= e(url('recepcion/reservas/' . $booking['id'])) ?>">
                                    <?= e(trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name'])) ?>
                                </a>
                                <span class="muted">· <?= e($booking['barber_name']) ?></span>
                            </span>
                            <span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php View::stop(); ?>
