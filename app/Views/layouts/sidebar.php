<?php
/**
 * Ruta: /app/Views/layouts/sidebar.php
 * Menú lateral construido según el ROL: cada perfil ve sólo lo suyo (spec §98).
 */

use App\Support\Role;
use Core\Auth;

$role   = Auth::role();
$active = $active ?? '';

/** @var array<string,array<int,array{label:string,href:string,icon:string,key:string}>> */
$menu = [];

if (Auth::hasRole(Role::ADMIN, Role::SUPER_ADMIN)) {
    $menu['Operación'] = ['_panel' => '/admin', 'items' => [
        ['label' => 'Dashboard',  'href' => '/admin',             'icon' => 'bar-chart', 'key' => 'dashboard'],
        ['label' => 'Reservas',   'href' => '/admin/reservas',    'icon' => 'calendar', 'key' => 'bookings'],
        ['label' => 'Calendario', 'href' => '/admin/calendario',  'icon' => 'calendar-check', 'key' => 'calendar'],
        ['label' => 'Clientes',   'href' => '/admin/clientes',    'icon' => 'users', 'key' => 'customers'],
        ['label' => 'Pagos',      'href' => '/admin/pagos',       'icon' => 'credit-card', 'key' => 'payments'],
    ]];

    $menu['Configuración'] = ['_panel' => '/admin', 'items' => [
        ['label' => 'Barberos',      'href' => '/admin/barberos',      'icon' => 'scissors', 'key' => 'barbers'],
        ['label' => 'Servicios',     'href' => '/admin/servicios',     'icon' => 'bottle', 'key' => 'services'],
        ['label' => 'Bloqueos',      'href' => '/admin/bloqueos',      'icon' => 'ban', 'key' => 'blocks'],
        ['label' => 'Usuarios',      'href' => '/admin/usuarios',      'icon' => 'lock', 'key' => 'users'],
        ['label' => 'Ajustes',       'href' => '/admin/configuracion', 'icon' => 'settings', 'key' => 'settings'],
    ]];

    $menu['Análisis'] = ['_panel' => '/admin', 'items' => [
        ['label' => 'Reportes',  'href' => '/admin/reportes',  'icon' => 'trending-up', 'key' => 'reports'],
        ['label' => 'Auditoría', 'href' => '/admin/auditoria', 'icon' => 'receipt', 'key' => 'activity'],
    ]];
}

if (Auth::hasRole(Role::RECEPTION) || Auth::hasRole(Role::ADMIN, Role::SUPER_ADMIN)) {
    $menu['Recepción'] = ['_panel' => '/recepcion', 'items' => [
        ['label' => 'Hoy',            'href' => '/recepcion',           'icon' => 'zap', 'key' => 'dashboard'],
        ['label' => 'Agenda',         'href' => '/recepcion/agenda',    'icon' => 'list', 'key' => 'agenda'],
        ['label' => 'Reservas',       'href' => '/recepcion/reservas',  'icon' => 'calendar', 'key' => 'bookings'],
        ['label' => 'Nueva reserva',  'href' => '/recepcion/reservas/nueva', 'icon' => 'plus', 'key' => 'new-booking'],
        ['label' => 'Walk-in',        'href' => '/recepcion/walk-in',   'icon' => 'walk', 'key' => 'walkin'],
        ['label' => 'Clientes',       'href' => '/recepcion/clientes',  'icon' => 'users', 'key' => 'customers'],
        ['label' => 'Bloquear hora',  'href' => '/recepcion/bloqueos',  'icon' => 'ban', 'key' => 'blocks'],
    ]];
}

// El panel del barbero se ofrece a quien tiene ficha propia; un administrador
// puede igualmente inspeccionar cualquier agenda con ?barber_id=.
if (Auth::hasRole(Role::BARBER) || Auth::barberId() !== null) {
    $menu['Mi panel'] = ['_panel' => '/barbero', 'items' => [
        ['label' => 'Mi agenda',   'href' => '/barbero/agenda',   'icon' => 'file-text', 'key' => 'agenda'],
        ['label' => 'Mis clientes', 'href' => '/barbero/clientes', 'icon' => 'user', 'key' => 'customers'],
        ['label' => 'Mi horario',  'href' => '/barbero/horario',  'icon' => 'clock', 'key' => 'schedule'],
        ['label' => 'Mis bloqueos', 'href' => '/barbero/bloqueos', 'icon' => 'ban', 'key' => 'blocks'],
    ]];
}

if (Auth::isSuperAdmin()) {
    $menu['Sistema'] = ['_panel' => '/super-admin', 'items' => [
        ['label' => 'Estado',       'href' => '/super-admin',              'icon' => 'server', 'key' => 'system'],
        ['label' => 'GitHub',       'href' => '/super-admin/github',       'icon' => 'github', 'key' => 'github'],
        ['label' => 'Despliegues',  'href' => '/super-admin/despliegues',  'icon' => 'rocket', 'key' => 'deployments'],
        ['label' => 'Migraciones',  'href' => '/super-admin/migraciones',  'icon' => 'database', 'key' => 'migrations'],
        ['label' => 'Respaldos',    'href' => '/super-admin/respaldos',    'icon' => 'save', 'key' => 'backups'],
        ['label' => 'Logs',         'href' => '/super-admin/logs',         'icon' => 'file-text', 'key' => 'logs'],
    ]];
}

$currentPath = '/' . trim(\Core\Request::current()?->path() ?? '', '/');

/** Panel actual: sólo el grupo que le corresponde puede marcar un ítem activo. */
$currentPanel = '/';
foreach (['/admin', '/recepcion', '/barbero', '/super-admin'] as $prefix) {
    if ($currentPath === $prefix || str_starts_with($currentPath, $prefix . '/')) {
        $currentPanel = $prefix;
        break;
    }
}
?>
<aside class="sidebar" aria-label="Menú del panel">
    <?php foreach ($menu as $group => $section): ?>
        <div class="nav-group">
            <div class="nav-group-title"><?= e($group) ?></div>
            <?php foreach ($section['items'] as $item): ?>
                <?php $isActive = $section['_panel'] === $currentPanel
                    && ($currentPath === $item['href'] || $active === $item['key']); ?>
                <a href="<?= e(url(ltrim($item['href'], '/'))) ?>" class="nav-item <?= $isActive ? 'is-active' : '' ?>">
                    <?= icon($item['icon'], 17) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="sidebar-foot">
        Flava Studio v<?= e(config('version.version')) ?>
        <?php if (config('app.env') !== 'production'): ?>
            <span class="badge badge-pending" style="margin-top:6px"><?= e(config('app.env')) ?></span>
        <?php endif; ?>
    </div>
</aside>
