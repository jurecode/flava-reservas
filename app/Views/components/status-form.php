<?php
/**
 * Ruta: /app/Views/components/status-form.php
 * Botones de cambio de estado según las transiciones permitidas.
 *
 * @var array  $booking
 * @var array  $states   estados destino permitidos
 * @var string $action   URL del POST
 */

use App\Support\BookingStatus;
use App\Support\Icon;
?>
<div class="row gap-sm">
    <?php foreach ($states as $state): ?>
        <?php $destructive = in_array($state, [BookingStatus::NO_SHOW, BookingStatus::CANCELLED], true); ?>
        <form method="post" action="<?= e($action) ?>" style="display:inline"
              <?php if ($destructive): ?>data-confirm="¿Marcar la reserva como <?= e(BookingStatus::label($state)) ?>?"<?php endif; ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= e($state) ?>">
            <button type="submit" class="btn btn-sm <?= $destructive ? 'btn-ghost' : ($state === BookingStatus::COMPLETED ? 'btn-dark' : 'btn-light') ?>">
                <?= icon(Icon::forBookingStatus($state), 15) ?>
                <?= e(BookingStatus::label($state)) ?>
            </button>
        </form>
    <?php endforeach; ?>
</div>
