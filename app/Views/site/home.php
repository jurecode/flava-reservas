<?php
/**
 * Ruta: /app/Views/site/home.php
 */

use App\Support\Str;
use Core\View;

View::layout('site');
View::start('content');
?>

<section class="hero honeycomb">
    <div class="container">
        <div class="hero-inner">
            <?php $ubicacion = \App\Models\Branch::locationLabel($branch); ?>
            <span class="hero-eyebrow">
                <?= icon('map-pin', 12) ?>
                Barbería<?= $ubicacion !== null ? ' · ' . e($ubicacion) : '' ?>
            </span>
            <h1>Tu estilo.<em>Tu momento.</em></h1>
            <p>Reserva online en 30 segundos. Elige tu servicio, tu barbero y la hora que te acomoda. Sin llamadas, sin esperas.</p>

            <div class="hero-actions">
                <a href="<?= e(url('reservar')) ?>" class="btn btn-primary btn-lg">Reservar ahora</a>
                <a href="<?= e(url('servicios')) ?>" class="btn btn-ghost btn-lg" style="color:#FFFDF5;border-color:rgba(255,255,255,.22)">Ver servicios</a>
            </div>

            <div class="hero-stats">
                <div class="hero-stat">
                    <strong><?= count($barbers) ?></strong>
                    <span>Barberos</span>
                </div>
                <div class="hero-stat">
                    <strong><?= count($services) ?></strong>
                    <span>Servicios</span>
                </div>
                <div class="hero-stat">
                    <strong>30s</strong>
                    <span>Para reservar</span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($featured !== []): ?>
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Lo más pedido</span>
            <h2>Servicios destacados</h2>
            <p>Precios claros y duración real. Toca uno y reservas de inmediato.</p>
        </div>

        <div class="showcase">
            <?php foreach (array_slice($featured, 0, 3) as $index => $service): ?>
                <?php $showDesc = $index === 0; require View::path('components.service-tile'); ?>
            <?php endforeach; ?>
        </div>

        <div class="center mt-3">
            <a href="<?= e(url('servicios')) ?>" class="btn btn-ghost">
                Ver todos los servicios <?= icon('arrow-right', 15) ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($barbers !== []): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">El equipo</span>
            <h2>Nuestros barberos</h2>
            <p>Elige con quién quieres cortarte. Cada uno tiene su estilo.</p>
        </div>

        <div class="barber-grid">
            <?php foreach (array_slice($barbers, 0, 4) as $barber): ?>
                <article class="barber-card">
                    <?php if (!empty($barber['photo'])): ?>
                        <img src="<?= e(upload_url($barber['photo'])) ?>" alt="<?= e($barber['display_name']) ?>" class="barber-photo" loading="lazy">
                    <?php else: ?>
                        <div class="barber-photo"><?= \App\Support\Cover::initials($barber['slug'], Str::initials($barber['first_name'], $barber['last_name'])) ?></div>
                    <?php endif; ?>

                    <div class="info">
                        <h3><?= e($barber['display_name']) ?></h3>
                        <p class="specialty"><?= e($barber['specialty'] ?: 'Barbería clásica y moderna') ?></p>
                        <a href="<?= e(url('reservar?barbero=' . $barber['slug'])) ?>" class="btn btn-outline-yellow btn-sm btn-block">Reservar</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Así de simple</span>
            <h2>Reservar toma 30 segundos</h2>
        </div>

        <div class="grid-4">
            <?php foreach ([
                ['1', 'Elige tu servicio', 'Corte, barba o el combo completo.'],
                ['2', 'Elige tu barbero', 'O deja que te asignemos el primero disponible.'],
                ['3', 'Elige fecha y hora', 'Sólo verás horarios realmente disponibles.'],
                ['4', 'Listo', 'Sin crear cuenta. Recibes tu código y te esperamos.'],
            ] as [$number, $stepTitle, $text]): ?>
                <div class="card center">
                    <div class="avatar" style="margin:0 auto 12px;background:var(--flava-yellow);color:var(--flava-black)"><?= $number ?></div>
                    <h3 style="font-size:1rem"><?= e($stepTitle) ?></h3>
                    <p class="small muted mb-0"><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="center mt-4">
            <a href="<?= e(url('reservar')) ?>" class="btn btn-primary btn-lg">Reservar mi hora</a>
        </div>
    </div>
</section>

<section class="section honeycomb" style="color:var(--flava-white)">
    <div class="container">
        <div class="grid-2 gap-lg" style="align-items:center">
            <div>
                <h2 style="color:var(--flava-white)">Dónde estamos</h2>
                <p style="color:rgba(255,253,245,.72)"><?= e($business['address']) ?></p>

                <div class="stack-sm mt-2">
                    <?php if ($business['phone']): ?>
                        <a href="tel:<?= e(Str::phone($business['phone'])) ?>" style="color:var(--flava-yellow)"><?= icon('phone', 14) ?> <?= e(Str::phoneDisplay($business['phone'])) ?></a>
                    <?php endif; ?>
                    <?php if ($business['email']): ?>
                        <a href="mailto:<?= e($business['email']) ?>" style="color:rgba(255,253,245,.72)"><?= icon('mail', 14) ?> <?= e($business['email']) ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($business['maps_url']): ?>
                    <a href="<?= e($business['maps_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline-yellow mt-3" style="color:#FFFDF5">Cómo llegar</a>
                <?php endif; ?>
            </div>

            <?php if ($business['policy']): ?>
                <div class="card card-dark">
                    <h3 style="color:var(--flava-yellow);font-size:.78rem;letter-spacing:.1em;text-transform:uppercase">Antes de venir</h3>
                    <p class="mb-0" style="color:rgba(255,253,245,.78);white-space:pre-line"><?= e($business['policy']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
View::stop();
