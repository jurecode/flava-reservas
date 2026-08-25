<?php
/**
 * Ruta: /routes/install.php
 *
 * Asistente de instalación. Estas rutas sólo responden mientras no exista
 * /config/installed.php (ver App\Middleware\InstallMiddleware).
 */

use Core\App;

/** @var \Core\Router $router */
$router = App::instance()->router();

$router->group(['prefix' => 'instalar'], function ($router): void {

    $router->get('/', 'InstallController@index')->name('install');

    // 1 · Requisitos del servidor
    $router->get('/requisitos', 'InstallController@requirements')->name('install.requirements');

    // 2 · Base de datos (creada a mano en el panel del hosting)
    $router->get('/base-de-datos',            'InstallController@database')->name('install.database');
    $router->post('/base-de-datos',           'InstallController@testDatabase');
    $router->get('/base-de-datos/confirmar',  'InstallController@confirmDatabase');

    // 3 · Estructura de tablas
    $router->get('/esquema',           'InstallController@schema')->name('install.schema');
    $router->post('/esquema',          'InstallController@importSchema');

    // 4 · Cuenta de administrador
    $router->get('/administrador',     'InstallController@admin')->name('install.admin');
    $router->post('/administrador',    'InstallController@storeAdmin');

    // 5 · Datos del negocio
    $router->get('/negocio',           'InstallController@business')->name('install.business');
    $router->post('/negocio',          'InstallController@storeBusiness');

    // 6 · Cierre
    $router->get('/finalizar',         'InstallController@finish')->name('install.finish');
    $router->post('/finalizar',        'InstallController@lock');
});
