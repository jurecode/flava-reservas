<?php
/**
 * Ruta: /app/Views/layouts/site.php
 * Layout del sitio público de Flava Studio.
 */

use Core\View;

$business = $business ?? \App\Services\SettingService::business();
?><!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(config('app.url')) ?>">

    <title><?= e($title ?? ($business['name'] . ' | Reserva tu hora online')) ?></title>
    <meta name="description" content="<?= e($description ?? 'Reserva tu hora en ' . $business['name'] . '. Elige tu servicio, barbero, fecha y horario disponible.') ?>">
    <link rel="canonical" href="<?= e(url(ltrim(\Core\Request::current()?->path() ?? '/', '/'))) ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($business['name']) ?>">
    <meta property="og:title" content="<?= e($title ?? $business['name']) ?>">
    <meta property="og:description" content="<?= e($description ?? $business['tagline']) ?>">
    <meta property="og:url" content="<?= e(config('app.url')) ?>">
    <meta name="theme-color" content="#0D0D0D">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/flava.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">

    <script type="application/ld+json">
    <?= json_encode([
        '@context'  => 'https://schema.org',
        '@type'     => 'HairSalon',
        'name'      => $business['name'],
        'url'       => config('app.url'),
        'telephone' => $business['phone'],
        'email'     => $business['email'],
        'address'   => ['@type' => 'PostalAddress', 'streetAddress' => $business['address'], 'addressCountry' => 'CL'],
        'priceRange' => '$$',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
</head>
<body>
<a class="skip-link" href="#main">Ir al contenido</a>

<?php require View::path('layouts.navbar'); ?>

<main id="main">
    <?= View::section('content') ?>
</main>

<?php require View::path('layouts.footer'); ?>

<script src="<?= e(asset('js/flava.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
