<?php
/**
 * Ruta: /app/Controllers/Admin/DashboardController.php
 * Dashboard con KPIs reales (spec §34).
 */

namespace App\Controllers\Admin;

use App\Models\Booking;
use App\Models\Branch;
use App\Services\DashboardService;
use Core\Controller;
use Core\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $dashboard = new DashboardService();
        $branchId  = Branch::defaultId();

        return $this->view('admin.dashboard', [
            'title'    => 'Dashboard',
            'active'   => 'dashboard',
            'stats'    => $dashboard->adminSummary($branchId),
            'todayAgenda' => (new Booking())->agendaForDate(today(), $branchId),
            'upcoming' => (new Booking())->upcoming(8, null, $branchId),
        ]);
    }
}
