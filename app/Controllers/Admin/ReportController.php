<?php
/**
 * Ruta: /app/Controllers/Admin/ReportController.php
 * Reportes operativos y auditoría (spec §60, §93).
 */

namespace App\Controllers\Admin;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\DateHelper;
use Core\Controller;
use Core\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $from = (string) ($this->request->input('from') ?: DateHelper::startOfMonth(today()));
        $to   = (string) ($this->request->input('to') ?: today());

        $dashboard = new DashboardService();
        $branchId  = Branch::defaultId();

        return $this->view('admin.reports', [
            'title'     => 'Reportes',
            'active'    => 'reports',
            'from'      => $from,
            'to'        => $to,
            'stats'     => $dashboard->periodStats($from, $to, $branchId),
            'barbers'   => $dashboard->barberRanking($from, $to, $branchId),
            'services'  => $dashboard->topServices($from, $to, $branchId, 10),
            'hours'     => $dashboard->busiestHours($from, $to, $branchId),
            'customers' => $dashboard->frequentCustomers(10),
            'series'    => $dashboard->revenueSeries(30, $branchId),
        ]);
    }

    public function activity(): Response
    {
        $filters = $this->request->only(['user_id', 'action', 'entity_type', 'entity_id', 'date_from']);

        return $this->view('admin.activity', [
            'title'   => 'Auditoría',
            'active'  => 'activity',
            'result'  => (new ActivityLog())->paginateFiltered($filters, max(1, $this->request->integer('page', 1)), 50),
            'filters' => $filters,
            'users'   => (new User())->listWithBarber([]),
        ]);
    }
}
