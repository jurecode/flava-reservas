<?php
/**
 * Ruta: /app/Views/components/booking-row.php
 * Fila de reserva reutilizada por las agendas de admin, recepción y barbero.
 *
 * @var array  $booking
 * @var string $basePath    '/admin' | '/recepcion'
 * @var bool   $showBarber
 */

use App\Support\BookingStatus;
use App\Support\PaymentStatus;
use App\Support\Str;

$basePath   = $basePath ?? '/admin';
$showBarber = $showBarber ?? true;
$name       = trim(($booking['customer_first_name'] ?? '') . ' ' . ($booking['customer_last_name'] ?? ''));
?>
<div class="slot-row status-<?= e($booking['status']) ?>">
    <div class="slot-time">
        <?= e(time_hm($booking['start_time'])) ?>
        <small><?= e(time_hm($booking['end_time'])) ?></small>
    </div>

    <div class="slot-info">
        <div class="who">
            <a href="<?= e(url(ltrim($basePath, '/') . '/reservas/' . $booking['id'])) ?>"><?= e($name) ?></a>
            <?php if ((int) ($booking['customer_no_shows'] ?? 0) >= 2): ?>
                <span class="badge badge-noshow" title="Cliente con inasistencias">
                    <?= icon('alert', 11) ?><?= (int) $booking['customer_no_shows'] ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="what">
            <span><?= icon('scissors', 13) ?> <?= e($booking['service_name']) ?></span>
            <?php if ($showBarber): ?><span><?= icon('user', 13) ?> <?= e($booking['barber_name']) ?></span><?php endif; ?>
            <?php if (!empty($booking['customer_phone'])): ?>
                <a href="tel:<?= e($booking['customer_phone']) ?>" class="muted">
                    <?= icon('phone', 13) ?> <?= e(Str::phoneDisplay($booking['customer_phone'])) ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($booking['customer_notes'])): ?>
            <div class="small muted mt-1"><?= icon('message', 13) ?> <?= e(Str::limit($booking['customer_notes'], 90)) ?></div>
        <?php endif; ?>
    </div>

    <div class="slot-side">
        <span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span>
        <?php if ($booking['payment_status'] === PaymentStatus::PAID): ?>
            <span class="badge badge-paid"><?= icon('check', 11) ?>Pagado</span>
        <?php else: ?>
            <span class="small muted"><?= e(money($booking['total'])) ?></span>
        <?php endif; ?>
    </div>
</div>
