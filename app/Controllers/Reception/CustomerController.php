<?php
/**
 * Ruta: /app/Controllers/Reception/CustomerController.php
 * Recepción usa el mismo CRM que administración, con sus propias vistas.
 */

namespace App\Controllers\Reception;

class CustomerController extends \App\Controllers\Admin\CustomerController
{
    protected string $panel = 'reception';
    protected string $basePath = '/recepcion';
}
