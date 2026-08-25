<?php
/**
 * Ruta: /app/Views/admin/payments/index.php
 */

use App\Support\PaymentMethod;
use App\Support\PaymentStatus;
use Core\View;

View::layout('panel');
View::start('content');

$rows  = $result['data'];
$query = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
?>

<div class="page-head">
    <div>
        <h1>Pagos</h1>
        <p class="sub">
            <?= number_format((int) $result['total'], 0, ',', '.') ?> movimiento(s) ·
            Total cobrado: <strong><?= e(money($result['sum_paid'])) ?></strong>
        </p>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Código o cliente">
        </div>

        <div class="field">
            <label class="label" for="payment_method">Método</label>
            <select class="select" id="payment_method" name="payment_method" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($methods as $method): ?>
                    <option value="<?= e($method) ?>" <?= ($filters['payment_method'] ?? '') === $method ? 'selected' : '' ?>>
                        <?= e(PaymentMethod::label($method)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <label class="label" for="date_from">Desde</label>
            <input class="input" type="date" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>

        <div class="field">
            <label class="label" for="date_to">Hasta</label>
            <input class="input" type="date" id="date_to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
        </div>

        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Filtrar</button>
        </div>
    </div>
</form>

<div class="card card-flush">
    <?php if ($rows === []): ?>
        <?php $icon = 'credit-card'; $message = 'Sin pagos en este periodo'; require View::path('components.empty'); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Reserva</th>
                        <th>Cliente</th>
                        <th>Barbero</th>
                        <th>Método</th>
                        <th>Estado</th>
                        <th class="right">Monto</th>
                        <th>Registró</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $payment): ?>
                        <tr>
                            <td class="small nowrap"><?= e(substr((string) ($payment['paid_at'] ?: $payment['created_at']), 0, 16)) ?></td>
                            <td class="mono small">
                                <?php if (!empty($payment['public_code'])): ?>
                                    <a href="<?= e(url('admin/reservas/' . $payment['booking_id'])) ?>"><?= e($payment['public_code']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="small"><?= e(trim(($payment['customer_first_name'] ?? '') . ' ' . ($payment['customer_last_name'] ?? ''))) ?></td>
                            <td class="small"><?= e($payment['barber_name'] ?? '—') ?></td>
                            <td class="small"><?= icon(PaymentMethod::icon($payment['payment_method']), 14) ?> <?= e(PaymentMethod::label($payment['payment_method'])) ?></td>
                            <td><span class="badge <?= e(PaymentStatus::badgeClass($payment['status'])) ?>"><?= e(PaymentStatus::label($payment['status'])) ?></span></td>
                            <td class="right bold nowrap"><?= e(money($payment['amount'])) ?></td>
                            <td class="small muted"><?= e($payment['registered_by_name'] ?? 'Sistema') ?></td>
                            <td class="right">
                                <?php if ($payment['status'] === PaymentStatus::PAID): ?>
                                    <form method="post" action="<?= e(url('admin/pagos/' . $payment['id'] . '/reembolso')) ?>"
                                          data-confirm="¿Registrar el reembolso de <?= e(money($payment['amount'])) ?>?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-ghost">Reembolsar</button>
                                    </form>
                                <?php endif; ?>
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
