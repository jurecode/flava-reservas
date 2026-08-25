<?php
/**
 * Ruta: /app/Views/components/service-tile.php
 * Tarjeta de servicio con imagen. La foto sube desde administración; si el
 * servicio aún no tiene, App\Support\Cover genera una portada de marca.
 *
 * @var array $service
 * @var bool  $showDesc  mostrar la descripción (sólo en la tarjeta grande)
 */

use App\Support\Cover;
use App\Support\Str;

$showDesc = $showDesc ?? false;
$photo    = upload_url($service['image'] ?? null);
?>
<a class="tile" href="<?= e(url('reservar?servicio=' . $service['slug'])) ?>"
   aria-label="Reservar <?= e($service['name']) ?>">

    <?php if ((int) ($service['is_featured'] ?? 0) === 1): ?>
        <span class="tile-tag"><?= icon('star', 11) ?> Destacado</span>
    <?php elseif (!empty($service['category_name'])): ?>
        <span class="tile-tag tile-tag-soft"><?= e($service['category_name']) ?></span>
    <?php endif; ?>

    <span class="tile-action" aria-hidden="true"><?= icon('arrow-right', 16) ?></span>

    <div class="tile-media">
        <?php if ($photo !== null): ?>
            <img src="<?= e($photo) ?>" alt="<?= e($service['name']) ?>" loading="lazy">
        <?php else: ?>
            <?= Cover::render($service['slug'] ?? $service['name']) ?>
        <?php endif; ?>
    </div>

    <div class="tile-body">
        <div class="tile-text">
            <h3 class="tile-name"><?= e($service['name']) ?></h3>

            <div class="tile-meta">
                <span><?= icon('clock', 13) ?> <?= (int) $service['duration_minutes'] ?> min</span>
                <?php if (!empty($service['category_name']) && (int) ($service['is_featured'] ?? 0) === 1): ?>
                    <span><?= e($service['category_name']) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($showDesc && !empty($service['description'])): ?>
                <p class="tile-desc"><?= e(Str::limit($service['description'], 120)) ?></p>
            <?php endif; ?>
        </div>

        <span class="tile-price"><?= e(money($service['price'])) ?></span>
    </div>
</a>
