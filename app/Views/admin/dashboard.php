<?php
/**
 * Ruta: /app/Views/admin/dashboard.php
 * KPIs reales del negocio (spec §34).
 */

use Core\View;

View::layout('panel');
View::start('content');

$today = $stats['today'];
$week  = $stats['week'];
$month = $stats['month'];
$maxRevenue = max(array_map(static fn (array $d): float => (float) $d['revenue'], $stats['revenue_series'])) ?: 1;
?>

<div class="page-head">
    <div>
        <h1>Hola, <?= e(explode(' ', \Core\Auth::displayName())[0] ?? '') ?></h1>
        <p class="sub"><?= e(ucfirst(date_es(today(), true))) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/reservas/nueva')) ?>" class="btn btn-primary btn-sm">+ Nueva reserva</a>
        <a href="<?= e(url('admin/calendario')) ?>" class="btn btn-ghost btn-sm">Ver calendario</a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Reservas hoy</span>
            <span class="ico-box"><?= icon('calendar', 15) ?></span>
        </div>
        <div class="v"><?= (int) $today['bookings'] ?></div>
        <div class="d"><?= (int) $today['completed'] ?> finalizadas</div>
    </div>

    <div class="kpi kpi-info">
        <div class="kpi-head">
            <span class="k">Reservas semana</span>
            <span class="ico-box"><?= icon('calendar-check', 15) ?></span>
        </div>
        <div class="v"><?= (int) $week['bookings'] ?></div>
        <div class="d"><?= (int) $week['customers'] ?> clientes distintos</div>
    </div>

    <div class="kpi kpi-ok">
        <div class="kpi-head">
            <span class="k">Ingresos hoy</span>
            <span class="ico-box"><?= icon('trending-up', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.5rem"><?= e(money($today['revenue'])) ?></div>
        <div class="d">Mes: <?= e(money($month['revenue'])) ?></div>
    </div>

    <div class="kpi <?= $today['no_show_rate'] > 10 ? 'kpi-danger' : 'kpi-warn' ?>">
        <div class="kpi-head">
            <span class="k">Ocupación hoy</span>
            <span class="ico-box"><?= icon('pie-chart', 15) ?></span>
        </div>
        <div class="v"><?= e($stats['occupancy_today']) ?>%</div>
        <div class="d">No-show mes: <?= e($month['no_show_rate']) ?>%</div>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card card-flush">
        <div class="card-head">
            <h2>Agenda de hoy</h2>
            <a href="<?= e(url('admin/reservas?date_from=' . today() . '&date_to=' . today())) ?>" class="btn btn-xs btn-ghost">Ver todas</a>
        </div>

        <div class="card-body stack-sm" style="max-height:520px;overflow-y:auto">
            <?php if ($todayAgenda === []): ?>
                <?php $icon = 'calendar'; $message = 'Sin reservas para hoy'; $hint = 'Cuando entren reservas aparecerán aquí.'; require View::path('components.empty'); ?>
            <?php else: ?>
                <?php foreach ($todayAgenda as $booking): ?>
                    <?php $basePath = '/admin'; require View::path('components.booking-row'); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="row-between mb-2">
                <h2 style="font-size:1rem;margin:0">Ingresos · últimos 14 días</h2>
                <span class="small muted"><?= e(money(array_sum(array_column($stats['revenue_series'], 'revenue')))) ?></span>
            </div>

            <div class="bars">
                <?php foreach ($stats['revenue_series'] as $day): ?>
                    <div class="bar-col" title="<?= e($day['label'] . ': ' . money($day['revenue'])) ?>">
                        <div class="bar" style="height:<?= max(3, (int) (($day['revenue'] / $maxRevenue) * 108)) ?>px"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row-between tiny muted mt-1">
                <span><?= e($stats['revenue_series'][0]['label'] ?? '') ?></span>
                <span><?= e(end($stats['revenue_series'])['label'] ?? '') ?></span>
            </div>
        </div>

        <?php if ($stats['top_barber'] !== null): ?>
            <div class="card">
                <h2 style="font-size:1rem">Barbero con más carga</h2>
                <div class="row row-nowrap gap-sm mt-1">
                    <?php if (!empty($stats['top_barber']['photo'])): ?>
                        <img src="<?= e(upload_url($stats['top_barber']['photo'])) ?>" alt="" class="avatar">
                    <?php else: ?>
                        <span class="avatar" style="background:<?= e($stats['top_barber']['color']) ?>22;color:<?= e($stats['top_barber']['color']) ?>">
                            <?= e(mb_substr($stats['top_barber']['display_name'], 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                    <div class="grow">
                        <strong><?= e($stats['top_barber']['display_name']) ?></strong>
                        <div class="small muted"><?= (int) $stats['top_barber']['bookings'] ?> reservas esta semana</div>
                    </div>
                    <span class="bold"><?= e(money($stats['top_barber']['revenue'])) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($stats['top_services'] !== []): ?>
            <div class="card">
                <h2 style="font-size:1rem">Servicios más vendidos <span class="small muted">(mes)</span></h2>
                <div class="stack-sm mt-1">
                    <?php foreach ($stats['top_services'] as $service): ?>
                        <div class="row-between small">
                            <span><?= e($service['service_name']) ?></span>
                            <span class="bold"><?= (int) $service['bookings'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card card-flush mt-3">
    <div class="card-head">
        <h2>Próximas reservas</h2>
        <a href="<?= e(url('admin/reservas?upcoming=1&order=asc')) ?>" class="btn btn-xs btn-ghost">Ver todas</a>
    </div>

    <div class="card-body stack-sm">
        <?php if ($upcoming === []): ?>
            <?php $icon = 'bee'; $message = 'No hay reservas próximas'; require View::path('components.empty'); ?>
        <?php else: ?>
            <?php foreach ($upcoming as $booking): ?>
                <div class="slot-row status-<?= e($booking['status']) ?>">
                    <div class="slot-time">
                        <?= e(\App\Support\DateHelper::chipEs($booking['booking_date'])) ?>
                        <small><?= e(time_hm($booking['start_time'])) ?></small>
                    </div>
                    <div class="slot-info">
                        <div class="who">
                            <a href="<?= e(url('admin/reservas/' . $booking['id'])) ?>">
                                <?= e(trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name'])) ?>
                            </a>
                        </div>
                        <div class="what"><?= e($booking['service_name']) ?> · <?= e($booking['barber_name']) ?></div>
                    </div>
                    <div class="slot-side">
                        <span class="badge <?= e(\App\Support\BookingStatus::badgeClass($booking['status'])) ?>">
                            <?= e(\App\Support\BookingStatus::label($booking['status'])) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php View::stop(); ?>
