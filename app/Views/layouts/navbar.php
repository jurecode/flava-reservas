<?php
/**
 * Ruta: /app/Views/layouts/navbar.php
 */

$business = $business ?? \App\Services\SettingService::business();

$links = [
    '/servicios' => 'Servicios',
    '/barberos'  => 'Barberos',
    '/contacto'  => 'Contacto',
];
?>
<header class="site-nav">
    <div class="container site-nav-inner">
        <a href="<?= e(url('/')) ?>" class="brand" aria-label="<?= e($business['name']) ?>">
            <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" class="brand-mark" width="30" height="34">
            <span>FLAVA <em>STUDIO</em></span>
        </a>

        <nav class="site-links" aria-label="Navegación principal">
            <?php foreach ($links as $href => $label): ?>
                <a href="<?= e(url($href)) ?>" class="<?= route_is($href) ? 'is-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="row row-nowrap gap-sm">
            <a href="<?= e(url('reservar')) ?>" class="btn btn-primary btn-sm">Reservar</a>
            <button class="nav-toggle" data-nav-toggle aria-label="Abrir menú" aria-expanded="false">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<div class="mobile-menu">
    <div class="container">
        <?php foreach ($links as $href => $label): ?>
            <a href="<?= e(url($href)) ?>" class="<?= route_is($href) ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('mi-reserva')) ?>">Consultar mi reserva</a>
    </div>
</div>
