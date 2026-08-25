<?php
/**
 * Ruta: /routes/api.php
 *
 * API interna usada por el frontend (fetch) y base de la futura API pública
 * v1 que consumirá una app móvil (spec §72, §73).
 * Formato de respuesta uniforme: {success, message, data|errors}.
 */

use Core\App;

/** @var \Core\Router $router */
$router = App::instance()->router();

// ---------------------------------------------------------------------
//  API PÚBLICA DEL BOOKING (sin autenticación: el cliente es invitado)
// ---------------------------------------------------------------------
$router->group(['prefix' => 'api/v1', 'namespace' => 'Api'], function ($router): void {

    $router->get('/services',              'BookingApiController@services');
    $router->get('/barbers',               'BookingApiController@barbers');
    $router->get('/availability/dates',    'BookingApiController@dates');
    $router->get('/availability/slots',    'BookingApiController@slots');
    $router->get('/bookings/{code}',       'BookingApiController@show');

    // Crear reserva desde el frontend (protegida con CSRF)
    $router->group(['middleware' => ['csrf']], function ($router): void {
        $router->post('/bookings', 'BookingApiController@store');
    });
});

// ---------------------------------------------------------------------
//  API INTERNA (personal autenticado)
// ---------------------------------------------------------------------
$router->group([
    'prefix'     => 'api/v1/admin',
    'namespace'  => 'Api',
    'middleware' => ['auth'],
], function ($router): void {

    $router->get('/availability/slots',  'AdminApiController@slots');
    $router->get('/customers/search',    'AdminApiController@searchCustomers');
    $router->get('/calendar/events',     'AdminApiController@calendarEvents');
    $router->get('/services/{id}/barbers', 'AdminApiController@serviceBarbers');
    $router->get('/bookings/{id}',       'AdminApiController@booking');
    $router->get('/stats/summary',       'AdminApiController@summary');
});
