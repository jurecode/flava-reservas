<?php
/**
 * Ruta: /app/Views/site/services.php
 */

use App\Support\Str;
use Core\View;

View::layout('site');
View::start('content');

$grouped = [];
foreach ($services as $service) {
    $grouped[$service['category_name'] ?: 'Servicios'][] = $service;
}
?>

<section class="section honeycomb" style="color:var(--flava-white);padding:52px 0">
    <div class="container">
        <span class="hero-eyebrow">Precios y duración</span>
        <h1 style="color:var(--flava-white);margin-bottom:6px">Servicios</h1>
        <p style="color:rgba(255,253,245,.7);margin:0">Todo lo que hacemos, con su precio y tiempo real de atención.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if ($services === []): ?>
            <?php $icon = 'bottle'; $message = 'Todavía no hay servicios publicados'; require View::path('components.empty'); ?>
        <?php endif; ?>

        <?php $destacados = array_values(array_filter($services, static fn (array $s): bool => (int) $s['is_featured'] === 1)); ?>

        <?php if ($destacados !== []): ?>
            <div class="section-head">
                <span class="eyebrow">Lo más pedido</span>
                <h2 style="font-size:1.35rem">Destacados</h2>
            </div>

            <div class="showcase mb-3">
                <?php foreach (array_slice($destacados, 0, 3) as $index => $service): ?>
                    <?php $showDesc = $index === 0; require View::path('components.service-tile'); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($grouped as $category => $items): ?>
            <div class="mb-3">
                <div class="section-head">
                    <h2 style="font-size:1.25rem"><?= e($category) ?></h2>
                </div>

                <div class="stack-sm">
                    <?php foreach ($items as $service): ?>
                        <?php require View::path('components.service-row'); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php
View::stop();
