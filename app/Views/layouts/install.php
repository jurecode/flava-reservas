<?php
/**
 * Ruta: /app/Views/layouts/install.php
 * Layout del asistente de instalación: una sola columna, sin distracciones.
 */

use Core\View;

$steps = [
    1 => 'Requisitos',
    2 => 'Base de datos',
    3 => 'Tablas',
    4 => 'Administrador',
    5 => 'Tu barbería',
    6 => 'Listo',
];

$step = $step ?? 1;
?><!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(config('app.url')) ?>">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($title ?? 'Instalación') ?> · Flava Studio</title>
    <meta name="theme-color" content="#0D0D0D">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/flava.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/install.css')) ?>">
</head>
<body class="install-body">

<header class="install-top">
    <div class="container container-md">
        <div class="install-brand">
            <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="26" height="30">
            <span>FLAVA <em>STUDIO</em></span>
            <span class="install-version">Instalación v<?= e(config('version.version')) ?></span>
        </div>
    </div>
</header>

<div class="install-steps-bar">
    <div class="container container-md">
        <ol class="install-steps">
            <?php foreach ($steps as $number => $label): ?>
                <li class="install-step <?= $number < $step ? 'is-done' : ($number === (int) $step ? 'is-current' : '') ?>">
                    <span class="install-step-num">
                        <?= $number < $step ? icon('check', 13) : $number ?>
                    </span>
                    <span class="install-step-label"><?= e($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>

<main class="install-main">
    <div class="container container-md">
        <?php require View::path('components.flash'); ?>
        <?= View::section('content') ?>
    </div>
</main>

<footer class="install-foot">
    <div class="container container-md">
        <p class="tiny muted mb-0">
            ¿Algo no calza? Revisa <code>docs/HOSTINGER.md</code> en los archivos del proyecto.
        </p>
    </div>
</footer>

<script src="<?= e(asset('js/flava.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
