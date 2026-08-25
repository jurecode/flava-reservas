<?php
/**
 * Ruta: /app/Views/site/barbers.php
 */

use App\Support\Str;
use Core\View;

View::layout('site');
View::start('content');
?>

<section class="section honeycomb" style="color:var(--flava-white);padding:52px 0">
    <div class="container">
        <span class="hero-eyebrow">El equipo</span>
        <h1 style="color:var(--flava-white);margin-bottom:6px">Barberos</h1>
        <p style="color:rgba(255,253,245,.7);margin:0">Conoce a quienes te van a atender.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($barbers === []): ?>
            <?php $message = 'Pronto publicaremos al equipo'; require View::path('components.empty'); ?>
        <?php endif; ?>

        <div class="barber-grid">
            <?php foreach ($barbers as $barber): ?>
                <article class="barber-card">
                    <?php if (!empty($barber['photo'])): ?>
                        <img src="<?= e(upload_url($barber['photo'])) ?>" alt="<?= e($barber['display_name']) ?>" class="barber-photo" loading="lazy">
                    <?php else: ?>
                        <div class="barber-photo"><?= \App\Support\Cover::initials($barber['slug'], Str::initials($barber['first_name'], $barber['last_name'])) ?></div>
                    <?php endif; ?>

                    <div class="info">
                        <h3><?= e($barber['display_name']) ?></h3>
                        <p class="specialty"><?= e($barber['specialty'] ?: 'Barbería clásica y moderna') ?></p>

                        <?php if (!empty($barber['services'])): ?>
                            <p class="tiny muted mb-0">
                                <?= e(implode(' · ', array_slice(array_column($barber['services'], 'name'), 0, 3))) ?>
                            </p>
                        <?php endif; ?>

                        <div class="row gap-sm mt-2">
                            <a href="<?= e(url('barberos/' . $barber['slug'])) ?>" class="btn btn-ghost btn-sm grow">Ver perfil</a>
                            <a href="<?= e(url('reservar?barbero=' . $barber['slug'])) ?>" class="btn btn-primary btn-sm grow">Reservar</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php
View::stop();
