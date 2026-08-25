<?php
/**
 * Ruta: /app/Views/components/service-row.php
 * Fila compacta de servicio, para listados largos donde la tarjeta grande
 * obligaría a demasiado scroll.
 *
 * @var array $service
 */

use App\Support\Cover;
use App\Support\Str;

$photo = upload_url($service['image'] ?? null);
?>
<div class="service-row">
    <div class="service-row-media">
        <?php if ($photo !== null): ?>
            <img src="<?= e($photo) ?>" alt="" loading="lazy">
        <?php else: ?>
            <?= Cover::render($service['slug'] ?? $service['name']) ?>
        <?php endif; ?>
    </div>

    <div class="service-row-body">
        <h3><?= e($service['name']) ?></h3>
        <div class="meta">
            <span><?= icon('clock', 13) ?> <?= (int) $service['duration_minutes'] ?> min</span>
            <?php if (!empty($service['description'])): ?>
                <span><?= e(Str::limit($service['description'], 70)) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <span class="service-row-price"><?= e(money($service['price'])) ?></span>

    <a href="<?= e(url('reservar?servicio=' . $service['slug'])) ?>" class="btn btn-primary btn-sm">Reservar</a>
</div>
