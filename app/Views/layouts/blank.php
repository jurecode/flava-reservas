<?php
/**
 * Ruta: /app/Views/layouts/blank.php
 * Layout mínimo: login, recuperación y páginas de error.
 */

use Core\View;
?><!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(config('app.url')) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Flava Studio') . ' · Flava Studio') ?></title>
    <meta name="theme-color" content="#0D0D0D">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/flava.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
</head>
<body class="<?= e($bodyClass ?? 'honeycomb-light') ?>">
    <?= View::section('content') ?>
    <script src="<?= e(asset('js/flava.js')) ?>" defer></script>
</body>
</html>
