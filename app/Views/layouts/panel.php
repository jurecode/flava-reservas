<?php
/**
 * Ruta: /app/Views/layouts/panel.php
 * Layout compartido por administración, recepción, barbero y súper admin.
 */

use App\Support\Role;
use App\Support\Str;
use Core\Auth;
use Core\View;

$user = Auth::user() ?? [];
$role = Auth::role();
?><!DOCTYPE html>
<html lang="es-CL">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(config('app.url')) ?>">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e(($title ?? 'Panel') . ' · Flava Studio') ?></title>
    <meta name="theme-color" content="#0D0D0D">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/favicon.svg')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/flava.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/panel.css')) ?>">
    <?= View::section('head') ?>
</head>
<body>
<div class="panel">
    <header class="panel-top">
        <div class="panel-top-inner">
            <button class="panel-burger" data-sidebar-toggle aria-label="Abrir menú">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
            </button>

            <a href="<?= e(url(ltrim(Role::homeFor($role), '/'))) ?>" class="panel-brand">
                <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" width="24" height="27">
                <span>FLAVA <em>STUDIO</em></span>
                <span class="role-tag"><?= e(Role::label($role)) ?></span>
            </a>

            <div class="panel-user">
                <a href="<?= e(url('/')) ?>" class="btn btn-xs btn-ghost no-print" style="color:rgba(255,253,245,.7);border-color:rgba(255,255,255,.16)" target="_blank" rel="noopener">Ver sitio</a>

                <div class="user-menu">
                    <button class="row row-nowrap gap-sm" data-user-menu style="background:none;border:none;padding:0">
                        <span class="who">
                            <strong style="color:#FFFDF5"><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong>
                            <span><?= e(Role::label($role)) ?></span>
                        </span>
                        <span class="avatar"><?= e(Str::initials($user['first_name'] ?? '', $user['last_name'] ?? '')) ?></span>
                    </button>

                    <div class="user-drop">
                        <a href="<?= e(url('cuenta')) ?>"><?= icon('user', 15) ?> Mi cuenta</a>
                        <a href="<?= e(url('cuenta/password')) ?>"><?= icon('key', 15) ?> Cambiar contraseña</a>
                        <?php if (Auth::isSuperAdmin()): ?>
                            <div class="sep"></div>
                            <a href="<?= e(url('super-admin')) ?>"><?= icon('settings', 15) ?> Sistema</a>
                        <?php endif; ?>
                        <div class="sep"></div>
                        <form method="post" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icon('log-out', 15) ?> Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="panel-body">
        <div class="sidebar-overlay"></div>
        <?php require View::path('layouts.sidebar'); ?>

        <main class="panel-main">
            <?php require View::path('components.flash'); ?>
            <?= View::section('content') ?>
        </main>
    </div>
</div>

<?= View::section('modals') ?>

<script src="<?= e(asset('js/flava.js')) ?>" defer></script>
<script src="<?= e(asset('js/panel.js')) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
