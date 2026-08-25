<?php
/**
 * Ruta: /routes/web.php
 * Mapa de URLs del sitio y de los paneles internos.
 *
 * Toda ruta que modifica estado pasa por 'csrf'.
 * Toda ruta interna valida permisos en el BACKEND con 'auth' + 'role:...'.
 */

use Core\App;

/** @var \Core\Router $router */
$router = App::instance()->router();

// =====================================================================
//  SITIO PÚBLICO  (el cliente NO necesita registrarse — spec §10)
// =====================================================================
$router->group(['middleware' => ['maintenance']], function ($router): void {

    $router->get('/',            'HomeController@index')->name('home');
    $router->get('/servicios',   'HomeController@services')->name('services');
    $router->get('/barberos',    'HomeController@barbers')->name('barbers');
    // Nota: el perfil usa /barberos/{slug} (plural) para no colisionar
    // con el panel del barbero, que vive bajo /barbero.
    $router->get('/barberos/{slug}', 'HomeController@barber')->name('barber.show');
    $router->get('/contacto',    'HomeController@contact')->name('contact');

    // ---- Flujo de reserva (paso a paso, mobile first) ----
    $router->get('/reservar',                  'BookingController@start')->name('booking.start');
    $router->get('/reservar/servicio',         'BookingController@start');
    $router->get('/reservar/barbero',          'BookingController@barber')->name('booking.barber');
    $router->get('/reservar/fecha',            'BookingController@date')->name('booking.date');
    $router->get('/reservar/checkout',         'BookingController@checkout')->name('booking.checkout');
    $router->post('/reservar/confirmar',       'BookingController@store')->name('booking.store');

    // ---- Gestión sin cuenta: código + token (spec §29) ----
    $router->get('/reserva/{code}',              'BookingController@show')->name('booking.show');
    $router->get('/reserva/{code}/reprogramar',  'BookingController@rescheduleForm')->name('booking.reschedule');
    $router->post('/reserva/{code}/reprogramar', 'BookingController@reschedule');
    $router->get('/reserva/{code}/cancelar',     'BookingController@cancelForm')->name('booking.cancel');
    $router->post('/reserva/{code}/cancelar',    'BookingController@cancel');
    $router->get('/reserva/{code}/calendario',   'BookingController@calendarFile')->name('booking.ics');
    $router->get('/mi-reserva',                  'BookingController@lookupForm')->name('booking.lookup');
    $router->post('/mi-reserva',                 'BookingController@lookup');
});

// =====================================================================
//  AUTENTICACIÓN DEL PERSONAL INTERNO
// =====================================================================
$router->get('/login',   'AuthController@showLogin')->name('login');
$router->post('/login',  'AuthController@login');
$router->post('/logout', 'AuthController@logout')->name('logout');
$router->get('/logout',  'AuthController@logout');

$router->get('/recuperar',          'AuthController@showForgot')->name('password.forgot');
$router->post('/recuperar',         'AuthController@sendReset');
$router->get('/restablecer/{token}', 'AuthController@showReset')->name('password.reset');
$router->post('/restablecer/{token}', 'AuthController@resetPassword');

$router->group(['middleware' => ['auth']], function ($router): void {
    $router->get('/cuenta',           'AuthController@profile')->name('account');
    $router->post('/cuenta',          'AuthController@updateProfile');
    $router->get('/cuenta/password',  'AuthController@showChangePassword')->name('account.password');
    $router->post('/cuenta/password', 'AuthController@changePassword');
});

// =====================================================================
//  ADMINISTRACIÓN  (/admin)
// =====================================================================
$router->group([
    'prefix'     => 'admin',
    'namespace'  => 'Admin',
    'middleware' => ['maintenance', 'auth', 'role:ADMIN|SUPER_ADMIN', 'csrf'],
], function ($router): void {

    $router->get('/', 'DashboardController@index')->name('admin');

    // ---- Reservas ----
    $router->get('/reservas',              'BookingController@index')->name('admin.bookings');
    $router->get('/reservas/nueva',        'BookingController@create')->name('admin.bookings.create');
    $router->post('/reservas',             'BookingController@store');
    $router->get('/reservas/{id}',         'BookingController@show')->name('admin.bookings.show');
    $router->post('/reservas/{id}',        'BookingController@update');
    $router->post('/reservas/{id}/estado', 'BookingController@changeStatus');
    $router->post('/reservas/{id}/reprogramar', 'BookingController@reschedule');
    $router->post('/reservas/{id}/barbero',     'BookingController@changeBarber');
    $router->post('/reservas/{id}/cancelar',    'BookingController@cancel');
    $router->post('/reservas/{id}/pago',        'BookingController@registerPayment');

    // ---- Calendario ----
    $router->get('/calendario', 'CalendarController@index')->name('admin.calendar');

    // ---- Clientes (CRM) ----
    $router->get('/clientes',            'CustomerController@index')->name('admin.customers');
    $router->get('/clientes/nuevo',      'CustomerController@create')->name('admin.customers.create');
    $router->post('/clientes',           'CustomerController@store');
    $router->get('/clientes/{id}',       'CustomerController@show')->name('admin.customers.show');
    $router->get('/clientes/{id}/editar', 'CustomerController@edit');
    $router->post('/clientes/{id}',      'CustomerController@update');
    $router->post('/clientes/{id}/nota', 'CustomerController@addNote');

    // ---- Barberos ----
    $router->get('/barberos',              'BarberController@index')->name('admin.barbers');
    $router->get('/barberos/nuevo',        'BarberController@create')->name('admin.barbers.create');
    $router->post('/barberos',             'BarberController@store');
    $router->get('/barberos/{id}/editar',  'BarberController@edit')->name('admin.barbers.edit');
    $router->post('/barberos/{id}',        'BarberController@update');
    $router->post('/barberos/{id}/estado', 'BarberController@toggleStatus');
    $router->get('/barberos/{id}/horario', 'ScheduleController@edit')->name('admin.barbers.schedule');
    $router->post('/barberos/{id}/horario', 'ScheduleController@update');

    // ---- Servicios ----
    $router->get('/servicios',              'ServiceController@index')->name('admin.services');
    $router->get('/servicios/nuevo',        'ServiceController@create')->name('admin.services.create');
    $router->post('/servicios',             'ServiceController@store');
    $router->get('/servicios/{id}/editar',  'ServiceController@edit')->name('admin.services.edit');
    $router->post('/servicios/{id}',        'ServiceController@update');
    $router->post('/servicios/{id}/estado', 'ServiceController@toggleStatus');

    // ---- Bloqueos de agenda ----
    $router->get('/bloqueos',            'ScheduleController@blocks')->name('admin.blocks');
    $router->post('/bloqueos',           'ScheduleController@storeBlock');
    $router->post('/bloqueos/{id}/eliminar', 'ScheduleController@deleteBlock');

    // ---- Pagos ----
    $router->get('/pagos',                 'PaymentController@index')->name('admin.payments');
    $router->post('/pagos/{id}/reembolso', 'PaymentController@refund');

    // ---- Usuarios internos ----
    $router->get('/usuarios',              'UserController@index')->name('admin.users');
    $router->get('/usuarios/nuevo',        'UserController@create')->name('admin.users.create');
    $router->post('/usuarios',             'UserController@store');
    $router->get('/usuarios/{id}/editar',  'UserController@edit')->name('admin.users.edit');
    $router->post('/usuarios/{id}',        'UserController@update');
    $router->post('/usuarios/{id}/estado', 'UserController@toggleStatus');

    // ---- Configuración ----
    $router->get('/configuracion',  'SettingsController@index')->name('admin.settings');
    $router->post('/configuracion', 'SettingsController@update');

    // ---- Reportes y auditoría ----
    $router->get('/reportes',  'ReportController@index')->name('admin.reports');
    $router->get('/auditoria', 'ReportController@activity')->name('admin.activity');
});

// =====================================================================
//  RECEPCIÓN  (/recepcion)
// =====================================================================
$router->group([
    'prefix'     => 'recepcion',
    'namespace'  => 'Reception',
    'middleware' => ['maintenance', 'auth', 'role:RECEPTION|ADMIN|SUPER_ADMIN', 'csrf'],
], function ($router): void {

    $router->get('/', 'DashboardController@index')->name('reception');

    $router->get('/reservas',              'BookingController@index')->name('reception.bookings');
    $router->get('/reservas/nueva',        'BookingController@create')->name('reception.bookings.create');
    $router->post('/reservas',             'BookingController@store');
    $router->get('/reservas/{id}',         'BookingController@show')->name('reception.bookings.show');
    $router->post('/reservas/{id}/estado', 'BookingController@changeStatus');
    $router->post('/reservas/{id}/reprogramar', 'BookingController@reschedule');
    $router->post('/reservas/{id}/barbero',     'BookingController@changeBarber');
    $router->post('/reservas/{id}/servicio',    'BookingController@changeService');
    $router->post('/reservas/{id}/cancelar',    'BookingController@cancel');
    $router->post('/reservas/{id}/pago',        'BookingController@registerPayment');
    $router->post('/reservas/{id}/nota',        'BookingController@addNote');

    $router->get('/agenda',    'DashboardController@agenda')->name('reception.agenda');
    $router->get('/walk-in',   'BookingController@walkIn')->name('reception.walkin');
    $router->post('/walk-in',  'BookingController@storeWalkIn');

    $router->get('/clientes',           'CustomerController@index')->name('reception.customers');
    $router->get('/clientes/nuevo',     'CustomerController@create')->name('reception.customers.create');
    $router->post('/clientes',          'CustomerController@store');
    $router->get('/clientes/{id}',      'CustomerController@show')->name('reception.customers.show');
    $router->post('/clientes/{id}',     'CustomerController@update');
    $router->post('/clientes/{id}/nota', 'CustomerController@addNote');

    $router->get('/bloqueos',  'DashboardController@blocks')->name('reception.blocks');
    $router->post('/bloqueos', 'DashboardController@storeBlock');
});

// =====================================================================
//  PANEL DEL BARBERO  (/barbero)
// =====================================================================
$router->group([
    'prefix'     => 'barbero',
    'namespace'  => 'Barber',
    'middleware' => ['maintenance', 'auth', 'role:BARBER|ADMIN|SUPER_ADMIN', 'csrf'],
], function ($router): void {

    $router->get('/',        'AgendaController@index')->name('barber');
    $router->get('/agenda',  'AgendaController@index')->name('barber.agenda');
    $router->post('/reservas/{id}/estado', 'AgendaController@changeStatus');
    $router->post('/reservas/{id}/nota',   'AgendaController@addNote');

    $router->get('/clientes',      'CustomerController@index')->name('barber.customers');
    $router->get('/clientes/{id}', 'CustomerController@show')->name('barber.customers.show');

    $router->get('/horario',  'AgendaController@schedule')->name('barber.schedule');
    $router->get('/bloqueos', 'AgendaController@blocks')->name('barber.blocks');
    $router->post('/bloqueos', 'AgendaController@storeBlock');
    $router->post('/bloqueos/{id}/eliminar', 'AgendaController@deleteBlock');
});

// =====================================================================
//  SÚPER ADMINISTRADOR  (/super-admin) — sólo funciones técnicas (spec §101)
// =====================================================================
$router->group([
    'prefix'     => 'super-admin',
    'namespace'  => 'SuperAdmin',
    'middleware' => ['auth', 'role:SUPER_ADMIN', 'csrf'],
], function ($router): void {

    $router->get('/', 'SystemController@index')->name('superadmin');

    // ---- GitHub y actualizaciones ----
    $router->get('/github',             'GitHubController@index')->name('superadmin.github');
    $router->post('/github/config',     'GitHubController@saveConfig');
    $router->post('/github/token',      'GitHubController@saveToken');
    $router->post('/github/token/eliminar', 'GitHubController@deleteToken');
    $router->post('/github/probar',     'GitHubController@testConnection');
    $router->post('/github/buscar',     'GitHubController@checkUpdates');

    // ---- Despliegues ----
    $router->get('/despliegues',        'DeploymentController@index')->name('superadmin.deployments');
    $router->post('/despliegues/ejecutar', 'DeploymentController@deploy');
    $router->post('/despliegues/{id}/rollback', 'DeploymentController@rollback');
    $router->get('/despliegues/{id}',   'DeploymentController@show')->name('superadmin.deployments.show');

    // ---- Sistema ----
    $router->get('/sistema',            'SystemController@info')->name('superadmin.system');
    $router->get('/migraciones',        'SystemController@migrations')->name('superadmin.migrations');
    $router->post('/migraciones/ejecutar', 'SystemController@runMigrations');
    $router->get('/respaldos',          'SystemController@backups')->name('superadmin.backups');
    $router->post('/respaldos/crear',   'SystemController@createBackup');
    $router->post('/respaldos/limpiar', 'SystemController@pruneBackups');
    $router->get('/logs',               'SystemController@logs')->name('superadmin.logs');
    $router->get('/rutas',              'SystemController@routes')->name('superadmin.routes');
    $router->post('/mantencion',        'SystemController@toggleMaintenance');
    $router->post('/cache/limpiar',     'SystemController@clearCache');
});

// Alias solicitado en la especificación (§104): /admin/system/github
$router->get('/admin/system/github', fn () => \Core\Response::redirect('/super-admin/github'));
