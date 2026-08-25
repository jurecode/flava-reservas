<?php
/**
 * Ruta: /app/Controllers/Admin/CalendarController.php
 * Calendario administrativo (spec §33). Los eventos llegan por AJAX desde
 * /api/v1/admin/calendar/events.
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Service;
use Core\Controller;
use Core\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin.calendar.index', [
            'title'    => 'Calendario',
            'active'   => 'calendar',
            'barbers'  => (new Barber())->activeList(),
            'services' => (new Service())->activeAll(),
            'date'     => (string) ($this->request->input('date') ?: today()),
            'view'     => (string) ($this->request->input('view') ?: 'timeGridDay'),
        ]);
    }
}
