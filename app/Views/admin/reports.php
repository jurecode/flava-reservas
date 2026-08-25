<?php
/**
 * Ruta: /app/Views/admin/reports.php
 * Reportes operativos (spec §93).
 */

use Core\View;

View::layout('panel');
View::start('content');

$maxRevenue = max(array_map(static fn (array $d): float => (float) $d['revenue'], $series)) ?: 1;
$maxHour    = max(array_map(static fn (array $h): int => (int) $h['bookings'], $hours ?: [['bookings' => 1]])) ?: 1;
?>

<div class="page-head">
    <div>
        <h1>Reportes</h1>
        <p class="sub"><?= e(date_es($from, true, false)) ?> → <?= e(date_es($to, true, false)) ?></p>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="from">Desde</label>
            <input class="input" type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div class="field">
            <label class="label" for="to">Hasta</label>
            <input class="input" type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Aplicar</button>
        </div>
    </div>
</form>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Reservas</span>
            <span class="ico-box"><?= icon('calendar', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['bookings'] ?></div>
        <div class="d"><?= (int) $stats['completed'] ?> finalizadas</div>
    </div>
    <div class="kpi kpi-ok">
        <div class="kpi-head">
            <span class="k">Ingresos</span>
            <span class="ico-box"><?= icon('trending-up', 15) ?></span>
        </div>
        <div class="v" style="font-size:1.5rem"><?= e(money($stats['revenue'])) ?></div>
        <div class="d">Ticket promedio: <?= e(money($stats['ticket'])) ?></div>
    </div>
    <div class="kpi kpi-info">
        <div class="kpi-head">
            <span class="k">Clientes atendidos</span>
            <span class="ico-box"><?= icon('users', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['customers'] ?></div>
        <div class="d">distintos en el periodo</div>
    </div>
    <div class="kpi <?= $stats['no_show_rate'] > 10 ? 'kpi-danger' : 'kpi-warn' ?>">
        <div class="kpi-head">
            <span class="k">Tasa de no-show</span>
            <span class="ico-box"><?= icon('ban', 15) ?></span>
        </div>
        <div class="v"><?= e($stats['no_show_rate']) ?>%</div>
        <div class="d"><?= (int) $stats['no_show'] ?> inasistencias · <?= (int) $stats['cancelled'] ?> canceladas</div>
    </div>
</div>

<div class="card mb-3">
    <h2 style="font-size:1rem">Ingresos diarios · últimos 30 días</h2>
    <div class="bars">
        <?php foreach ($series as $day): ?>
            <div class="bar-col" title="<?= e($day['label'] . ': ' . money($day['revenue']) . ' · ' . $day['bookings'] . ' reservas') ?>">
                <div class="bar" style="height:<?= max(3, (int) (($day['revenue'] / $maxRevenue) * 110)) ?>px"></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card card-flush">
        <div class="card-head"><h2>Ventas por barbero</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width:0">
                <thead>
                    <tr><th>Barbero</th><th class="right">Reservas</th><th class="right">No-show</th><th class="right">Ingresos</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($barbers as $barber): ?>
                        <tr>
                            <td>
                                <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= e($barber['color']) ?>;margin-right:7px"></span>
                                <?= e($barber['display_name']) ?>
                            </td>
                            <td class="right"><?= (int) $barber['bookings'] ?></td>
                            <td class="right"><?= (int) $barber['no_show'] ?></td>
                            <td class="right bold"><?= e(money($barber['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-head"><h2>Servicios más vendidos</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width:0">
                <thead>
                    <tr><th>Servicio</th><th class="right">Cantidad</th><th class="right">Ingresos</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td><?= e($service['service_name']) ?></td>
                            <td class="right"><?= (int) $service['bookings'] ?></td>
                            <td class="right bold"><?= e(money($service['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:1rem">Horas más solicitadas</h2>
        <div class="bars" style="height:120px">
            <?php foreach ($hours as $hour): ?>
                <div class="bar-col" title="<?= (int) $hour['hour'] ?>:00 · <?= (int) $hour['bookings'] ?> reservas">
                    <div class="bar" style="height:<?= max(3, (int) (($hour['bookings'] / $maxHour) * 96)) ?>px"></div>
                    <span class="tiny muted"><?= (int) $hour['hour'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card-flush">
        <div class="card-head"><h2>Clientes frecuentes</h2></div>
        <div class="table-wrap">
            <table class="table" style="min-width:0">
                <thead>
                    <tr><th>Cliente</th><th class="right">Visitas</th><th class="right">Gastado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('admin/clientes/' . $customer['id'])) ?>">
                                    <?= e(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?>
                                </a>
                            </td>
                            <td class="right"><?= (int) $customer['completed_bookings'] ?></td>
                            <td class="right bold"><?= e(money($customer['total_spent'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php View::stop(); ?>
