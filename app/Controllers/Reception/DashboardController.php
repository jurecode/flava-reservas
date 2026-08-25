<?php
/**
 * Ruta: /app/Controllers/Reception/DashboardController.php
 * Panel de recepción: operativo, centrado en el día (spec §35).
 */

namespace App\Controllers\Reception;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Branch;
use App\Services\ActivityLogger;
use App\Services\AvailabilityService;
use App\Services\DashboardService;
use App\Support\DateHelper;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $branchId = Branch::defaultId();
        $date     = (string) ($this->request->input('date') ?: today());

        return $this->view('reception.dashboard', [
            'title'    => 'Recepción',
            'active'   => 'dashboard',
            'date'     => $date,
            'stats'    => (new DashboardService())->receptionSummary($branchId),
            'bookings' => (new Booking())->agendaForDate($date, $branchId),
            'barbers'  => (new BarberSchedule())->barbersWorkingOn($date, $branchId),
        ]);
    }

    /** Agenda del día por barbero, en columnas. */
    public function agenda(): Response
    {
        $branchId     = Branch::defaultId();
        $date         = (string) ($this->request->input('date') ?: today());
        $availability = new AvailabilityService();
        $barbers      = (new BarberSchedule())->barbersWorkingOn($date, $branchId);
        $columns      = [];

        foreach ($barbers as $barber) {
            $columns[] = [
                'barber'    => $barber,
                'timeline'  => $availability->dayTimeline((int) $barber['id'], $date),
                'occupancy' => $availability->occupancyRate((int) $barber['id'], $date),
            ];
        }

        return $this->view('reception.agenda', [
            'title'   => 'Agenda · ' . DateHelper::longEs($date, false, true),
            'active'  => 'agenda',
            'date'    => $date,
            'columns' => $columns,
            'prev'    => DateHelper::make($date)->modify('-1 day')->format('Y-m-d'),
            'next'    => DateHelper::make($date)->modify('+1 day')->format('Y-m-d'),
        ]);
    }

    public function blocks(): Response
    {
        $from = (string) ($this->request->input('from') ?: today());
        $to   = (string) ($this->request->input('to') ?: DateHelper::make($from)->modify('+14 days')->format('Y-m-d'));

        return $this->view('reception.blocks', [
            'title'   => 'Bloquear horario',
            'active'  => 'blocks',
            'blocks'  => (new BlockedTime())->forRange($from, $to, null, Branch::defaultId()),
            'barbers' => (new Barber())->activeList(Branch::defaultId()),
            'types'   => BlockedTime::TYPES,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function storeBlock(): Response
    {
        $validator = new Validator($this->request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|time',
            'end_date'   => 'required|date_format:Y-m-d',
            'end_time'   => 'required|time',
            'type'       => 'required|in:' . implode(',', array_keys(BlockedTime::TYPES)),
            'barber_id'  => 'nullable|integer|exists:barbers,id',
            'reason'     => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $start = $this->request->input('start_date') . ' ' . substr((string) $this->request->input('start_time'), 0, 5) . ':00';
        $end   = $this->request->input('end_date') . ' ' . substr((string) $this->request->input('end_time'), 0, 5) . ':00';

        if ($start >= $end) {
            Session::flash('error', 'El término del bloqueo debe ser posterior a su inicio.');

            return $this->back();
        }

        $model     = new BlockedTime();
        $barberId  = $this->request->integer('barber_id');
        $conflicts = $model->conflictingBookings($barberId, $start, $end);

        if ($conflicts !== []) {
            Session::flash('error', sprintf(
                'Hay %d reserva(s) en ese rango (%s). Reprográmalas antes de bloquear.',
                count($conflicts),
                implode(', ', array_column($conflicts, 'public_code'))
            ));

            return $this->back();
        }

        $id = $model->create([
            'branch_id'      => Branch::defaultId(),
            'barber_id'      => $barberId,
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'type'           => (string) $this->request->input('type'),
            'reason'         => $this->request->input('reason') ?: null,
            'created_by'     => Auth::id(),
        ]);

        ActivityLogger::log('blocked.created', 'blocked_time', $id, 'Bloqueo creado desde recepción');

        return $this->redirectWith('/recepcion/bloqueos', 'Bloqueo creado.');
    }
}
