<?php
/**
 * Ruta: /app/Views/booking/barber.php
 * PASO 2 — Barbero (incluye "cualquier barbero disponible").
 */

use App\Support\Str;
use Core\View;

View::setMany(['step' => 2, 'stepName' => 'Barbero', 'backUrl' => url('reservar')]);
View::layout('booking');
View::start('content');
?>

<div class="booking-step-label">Paso 2</div>
<h1 class="booking-h1">¿Con quién te atiendes?</h1>

<div class="chosen">
    <span class="ico-box ico-box-accent"><?= icon('scissors', 17) ?></span>
    <div class="grow">
        <strong><?= e($service['name']) ?></strong>
        <span><?= (int) $service['duration_minutes'] ?> min · <?= e(money($service['price'])) ?></span>
    </div>
    <a href="<?= e(url('reservar')) ?>" class="btn btn-xs btn-ghost">Cambiar</a>
</div>

<?php if ($barbers === []): ?>
    <div class="card center">
        <p class="bold">Ningún barbero tiene habilitado este servicio.</p>
        <a href="<?= e(url('reservar')) ?>" class="btn btn-ghost btn-sm">Elegir otro servicio</a>
    </div>
<?php else: ?>
    <form method="get" action="<?= e(url('reservar/fecha')) ?>" id="barberForm">
        <input type="hidden" name="service_id" value="<?= (int) $service['id'] ?>">
        <input type="hidden" name="barber_id" data-pick-value value="<?= e($draft['barber_id'] ?? '') ?>">

        <div class="pick-list">
            <?php if ($allow_any): ?>
                <button type="button" class="pick" data-pick data-value="any" data-auto-advance="1">
                    <span class="pick-check"><?= icon('check', 14) ?></span>
                    <span class="avatar avatar-accent"><?= icon('users', 18) ?></span>
                    <div class="pick-body">
                        <span class="pick-title">Cualquier barbero disponible</span>
                        <span class="pick-meta">Te asignamos el primero que tenga tu hora</span>
                    </div>
                </button>
            <?php endif; ?>

            <?php foreach ($barbers as $barber): ?>
                <button type="button" class="pick <?= (int) ($draft['barber_id'] ?? 0) === (int) $barber['id'] ? 'is-selected' : '' ?>"
                        data-pick data-value="<?= (int) $barber['id'] ?>" data-auto-advance="1">
                    <span class="pick-check"><?= icon('check', 14) ?></span>
                    <?php if (!empty($barber['photo'])): ?>
                        <img src="<?= e(upload_url($barber['photo'])) ?>" alt="" class="avatar" width="44" height="44">
                    <?php else: ?>
                        <span class="avatar"><?= e(Str::initials($barber['first_name'], $barber['last_name'])) ?></span>
                    <?php endif; ?>

                    <div class="pick-body">
                        <span class="pick-title"><?= e($barber['display_name']) ?></span>
                        <span class="pick-meta"><?= e($barber['specialty'] ?: 'Barbería clásica y moderna') ?></span>
                        <?php if (!empty($barber['next_free'])): ?>
                            <span class="next-free"><?= icon('clock', 12) ?> Próximo: <?= e($barber['next_free']) ?></span>
                        <?php else: ?>
                            <span class="small muted">Sin horarios en los próximos días</span>
                        <?php endif; ?>
                    </div>

                    <?php if ((float) $barber['price'] !== (float) $service['price']): ?>
                        <span class="pick-price"><?= e(money($barber['price'])) ?></span>
                    <?php endif; ?>
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
            <strong><?= e($service['name']) ?></strong>
            <span><?= e(money($service['price'])) ?> · <?= (int) $service['duration_minutes'] ?> min</span>
        </div>
        <button type="submit" form="barberForm" class="btn btn-primary <?= empty($draft['barber_id']) ? 'is-disabled' : '' ?>"
                data-continue <?= empty($draft['barber_id']) ? 'disabled' : '' ?>>
            Continuar
        </button>
    </div>
</div>
<?php View::stop(); ?>
