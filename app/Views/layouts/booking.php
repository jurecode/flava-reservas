<?php
/**
 * Ruta: /app/Views/layouts/booking.php
 * Layout del flujo de reserva: pantalla enfocada, sin menús que distraigan.
 */

use Core\View;

$business = $business ?? \App\Services\SettingService::business();
$step      = $step ?? 1;
$showSteps = $showSteps ?? true;
$wide      = $wide ?? false;   // el checkout usa dos columnas
?><!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(config('app.url')) ?>">
    <meta name="robots" content="noindex">

    <title><?= e($title ?? 'Reservar hora | ' . $business['name']) ?></title>
    <meta name="description" content="<?= e($description ?? 'Reserva tu hora en ' . $business['name'] . ' en menos de un minuto.') ?>">
    <meta name="theme-color" content="#0D0D0D">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/flava.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body>
<div class="booking-shell">
    <header class="booking-top">
        <div class="container">
            <div class="booking-top-inner">
                <?php if (($backUrl ?? null) !== null): ?>
                    <a href="<?= e($backUrl) ?>" class="booking-back" aria-label="Volver">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('/')) ?>" class="booking-back" aria-label="Ir al inicio">
                        <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="19" height="21">
                    </a>
                <?php endif; ?>

                <div class="grow">
                    <div class="booking-title">FLAVA STUDIO</div>
                    <div class="booking-sub"><?= $showSteps ? 'Paso ' . (int) $step . ' de 4 · ' : '' ?><?= e($stepName ?? 'Reserva') ?></div>
                </div>

                <a href="<?= e(url('/')) ?>" class="tiny" style="color:rgba(255,253,245,.5)">Salir</a>
            </div>

            <?php if ($showSteps): ?>
                <div class="steps" role="progressbar" aria-valuenow="<?= (int) $step ?>" aria-valuemin="1" aria-valuemax="4">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <span class="step-bar <?= $i < $step ? 'is-done' : ($i === (int) $step ? 'is-current' : '') ?>"></span>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="booking-main">
        <div class="container <?= $wide ? '' : 'container-md' ?>">
            <?php require View::path('components.flash'); ?>
            <?= View::section('content') ?>
        </div>
    </main>

    <?= View::section('bar') ?>
</div>

<?= View::section('modals') ?>

<script src="<?= e(asset('js/flava.js')) ?>" defer></script>
<script src="<?= e(asset('js/booking.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
