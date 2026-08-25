<?php
/**
 * Ruta: /app/Views/site/contact.php
 */

use App\Support\Str;
use Core\View;

View::layout('site');
View::start('content');

$whatsapp = Str::whatsappLink($business['whatsapp'], 'Hola, quiero consultar por una hora en ' . $business['name']);
?>

<section class="section honeycomb" style="color:var(--flava-white);padding:52px 0">
    <div class="container">
        <span class="hero-eyebrow">Estamos cerca</span>
        <h1 style="color:var(--flava-white);margin-bottom:6px">Contacto</h1>
        <p style="color:rgba(255,253,245,.7);margin:0">Escríbenos o pásate directamente.</p>
    </div>
</section>

<section class="section">
    <div class="container container-md">
        <div class="grid-2">
            <div class="card">
                <h3>Dónde encontrarnos</h3>
                <div class="stack-sm">
                    <?php if ($business['address']): ?><p class="mb-0"><?= icon('map-pin', 14) ?> <?= e($business['address']) ?></p><?php endif; ?>
                    <?php if ($business['phone']): ?>
                        <p class="mb-0"><?= icon('phone', 14) ?> <a href="tel:<?= e(Str::phone($business['phone'])) ?>"><?= e(Str::phoneDisplay($business['phone'])) ?></a></p>
                    <?php endif; ?>
                    <?php if ($business['email']): ?>
                        <p class="mb-0"><?= icon('mail', 14) ?> <a href="mailto:<?= e($business['email']) ?>"><?= e($business['email']) ?></a></p>
                    <?php endif; ?>
                    <?php if ($business['instagram']): ?>
                        <p class="mb-0"><?= icon('instagram', 14) ?> <a href="https://instagram.com/<?= e(ltrim($business['instagram'], '@')) ?>" target="_blank" rel="noopener">@<?= e(ltrim($business['instagram'], '@')) ?></a></p>
                    <?php endif; ?>
                </div>

                <div class="row gap-sm mt-3">
                    <?php if ($whatsapp): ?>
                        <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">WhatsApp</a>
                    <?php endif; ?>
                    <?php if ($business['maps_url']): ?>
                        <a href="<?= e($business['maps_url']) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">Ver en el mapa</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card card-dark">
                <h3 style="color:var(--flava-yellow)">¿Quieres reservar?</h3>
                <p style="color:rgba(255,253,245,.75)">No necesitas llamar ni crear una cuenta. Reserva online en menos de un minuto y recibe tu código.</p>
                <a href="<?= e(url('reservar')) ?>" class="btn btn-primary btn-block">Reservar ahora</a>
                <a href="<?= e(url('mi-reserva')) ?>" class="btn btn-ghost btn-block mt-1" style="color:#FFFDF5;border-color:rgba(255,255,255,.2)">Consultar mi reserva</a>
            </div>
        </div>

        <?php if ($business['policy']): ?>
            <div class="card mt-3">
                <h3>Políticas de reserva</h3>
                <p class="mb-0 muted" style="white-space:pre-line"><?= e($business['policy']) ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
View::stop();
