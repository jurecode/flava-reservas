<?php
/**
 * Ruta: /app/Views/booking/service.php
 * PASO 1 — Servicio.
 */

use App\Support\Str;
use Core\View;

View::setMany(['step' => 1, 'stepName' => 'Servicio', 'backUrl' => url('/')]);
View::layout('booking');
View::start('content');
?>

<div class="booking-step-label">Paso 1</div>
<h1 class="booking-h1">¿Qué te hacemos hoy?</h1>

<?php if ($services === []): ?>
    <div class="card center">
        <p class="mb-0">Aún no hay servicios publicados. Escríbenos y coordinamos tu hora.</p>
    </div>
<?php else: ?>
    <form method="get" action="<?= e(url('reservar/barbero')) ?>" id="serviceForm">
        <input type="hidden" name="service_id" data-pick-value value="<?= e($draft['service_id'] ?? '') ?>">

        <div class="pick-list">
            <?php foreach ($services as $service): ?>
                <button type="button" class="pick <?= (int) ($draft['service_id'] ?? 0) === (int) $service['id'] ? 'is-selected' : '' ?>"
                        data-pick data-value="<?= (int) $service['id'] ?>" data-auto-advance="1">
                    <span class="pick-check"><?= icon('check', 14) ?></span>
                    <div class="pick-body">
                        <span class="pick-title"><?= e($service['name']) ?></span>
                        <span class="pick-meta">
                            <span><?= icon('clock', 13) ?> <?= (int) $service['duration_minutes'] ?> min</span>
                            <?php if (!empty($service['description'])): ?>
                                <span><?= e(Str::limit($service['description'], 42)) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="pick-price"><?= e(money($service['price'])) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </form>
<?php endif; ?>

<?php
View::stop();
View::start('bar');
?>
<div class="booking-bar">
    <div class="container container-md booking-bar-inner">
        <div class="summary">
            <strong>Elige un servicio</strong>
            <span>Precio y duración incluidos</span>
        </div>
        <button type="submit" form="serviceForm" class="btn btn-primary <?= empty($draft['service_id']) ? 'is-disabled' : '' ?>"
                data-continue <?= empty($draft['service_id']) ? 'disabled' : '' ?>>
            Continuar
        </button>
    </div>
</div>
<?php View::stop(); ?>
