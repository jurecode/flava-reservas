<?php
/**
 * Ruta: /app/Views/admin/bookings/index.php
 * Listado de reservas con filtros y paginación (spec §89).
 * Reutilizado por recepción mediante /app/Views/reception/bookings/index.php.
 */

use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\PaymentStatus;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');

$rows  = $result['data'];
$query = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
unset($query['branch_id']);
?>

<div class="page-head">
    <div>
        <h1>Reservas</h1>
        <p class="sub"><?= number_format((int) $result['total'], 0, ',', '.') ?> reserva(s) encontradas</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/nueva')) ?>" class="btn btn-primary btn-sm">+ Nueva reserva</a>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Código, nombre, RUT o teléfono">
        </div>

        <div class="field">
            <label class="label" for="barber_id">Barbero</label>
            <select class="select" id="barber_id" name="barber_id" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($barbers as $barber): ?>
                    <option value="<?= (int) $barber['id'] ?>" <?= (int) ($filters['barber_id'] ?? 0) === (int) $barber['id'] ? 'selected' : '' ?>>
                        <?= e($barber['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="status">Estado</label>
            <select class="select" id="status" name="status" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e(BookingStatus::label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="date_from">Desde</label>
            <input class="input" type="date" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>

        <div class="field">
            <label class="label" for="date_to">Hasta</label>
            <input class="input" type="date" id="date_to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>

        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Filtrar</button>
            <a href="<?= e(url(ltrim($basePath, '/') . '/reservas')) ?>" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </div>
</form>

<div class="card card-flush">
    <?php if ($rows === []): ?>
        <?php $icon = 'search'; $message = 'No encontramos reservas con esos filtros'; $hint = 'Prueba ampliando el rango de fechas.'; require View::path('components.empty'); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Barbero</th>
                        <th>Fecha y hora</th>
                        <th>Estado</th>
                        <th>Pago</th>
                        <th class="right">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $booking): ?>
                        <tr>
                            <td class="mono small bold"><?= e($booking['public_code']) ?></td>
                            <td>
                                <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $booking['customer_id'])) ?>" class="bold">
                                    <?= e(trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name'])) ?>
                                </a>
                                <?php if (!empty($booking['customer_phone'])): ?>
                                    <div class="tiny muted"><?= e(Str::phoneDisplay($booking['customer_phone'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e($booking['service_name']) ?></td>
                            <td class="small"><?= e($booking['barber_name']) ?></td>
                            <td class="small nowrap">
                                <?= e(\App\Support\DateHelper::shortEs($booking['booking_date'])) ?>
                                <strong><?= e(time_hm($booking['start_time'])) ?></strong>
                                <div class="tiny muted"><?= e(BookingSource::label($booking['source'])) ?></div>
                            </td>
                            <td><span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span></td>
                            <td><span class="badge <?= e(PaymentStatus::badgeClass($booking['payment_status'])) ?>"><?= e(PaymentStatus::label($booking['payment_status'])) ?></span></td>
                            <td class="right bold nowrap"><?= e(money($booking['total'])) ?></td>
                            <td class="right">
                                <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'])) ?>" class="btn btn-xs btn-light">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require View::path('components.pagination'); ?>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
