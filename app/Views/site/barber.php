<?php
/**
 * Ruta: /app/Views/site/barber.php
 */

use App\Support\Str;
use Core\View;

View::layout('site');
View::start('content');
?>

<section class="section honeycomb" style="color:var(--flava-white);padding:46px 0">
    <div class="container">
        <div class="row gap-lg" style="align-items:center">
            <?php if (!empty($barber['photo'])): ?>
                <img src="<?= e(upload_url($barber['photo'])) ?>" alt="<?= e($barber['display_name']) ?>"
                     style="width:118px;height:118px;border-radius:50%;object-fit:cover;border:3px solid var(--flava-yellow)">
            <?php else: ?>
                <div class="avatar avatar-xl" style="background:var(--flava-yellow);color:var(--flava-black)">
                    <?= e(Str::initials($barber['first_name'], $barber['last_name'])) ?>
                </div>
            <?php endif; ?>

            <div class="grow">
                <span class="hero-eyebrow">Barbero</span>
                <h1 style="color:var(--flava-white);margin-bottom:4px"><?= e($barber['display_name']) ?></h1>
                <p style="color:var(--flava-yellow);margin:0"><?= e($barber['specialty'] ?: 'Barbería clásica y moderna') ?></p>
                <?php if (!empty($barber['instagram'])): ?>
                    <a href="https://instagram.com/<?= e(ltrim($barber['instagram'], '@')) ?>" target="_blank" rel="noopener"
                       class="small" style="color:rgba(255,253,245,.6)">@<?= e(ltrim($barber['instagram'], '@')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container container-md">
        <?php if (!empty($barber['bio'])): ?>
            <div class="card mb-3">
                <p class="mb-0"><?= nl2br(e($barber['bio'])) ?></p>
            </div>
        <?php endif; ?>

        <h2>Servicios de <?= e($barber['display_name']) ?></h2>

        <div class="pick-list mt-2">
            <?php foreach ($services as $service): ?>
                <?php
                    $price    = $service['custom_price'] !== null ? $service['custom_price'] : $service['price'];
                    $duration = $service['custom_duration'] !== null ? $service['custom_duration'] : $service['duration_minutes'];
                ?>
                <a class="pick" href="<?= e(url('reservar?servicio=' . $service['slug'] . '&barbero=' . $barber['slug'])) ?>">
                    <div class="pick-body">
                        <span class="pick-title"><?= e($service['name']) ?></span>
                        <span class="pick-meta"><?= (int) $duration ?> min</span>
                    </div>
                    <span class="pick-price"><?= e(money($price)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="center mt-4">
            <a href="<?= e(url('reservar?barbero=' . $barber['slug'])) ?>" class="btn btn-primary btn-lg">
                Reservar con <?= e($barber['display_name']) ?>
            </a>
        </div>
    </div>
</section>

<?php
View::stop();
