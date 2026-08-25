<?php
/**
 * Ruta: /app/Controllers/Barber/AgendaController.php
 *
 * Panel del barbero (spec §17). Ve SOLAMENTE lo que necesita: su agenda,
 * el estado de cada cita y los datos pertinentes del cliente.
 */

namespace App\Controllers\Barber;

use App\Controllers\Barber\Concerns\ResolvesBarber;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\CustomerNote;
use App\Services\ActivityLogger;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\CustomerService;
use App\Services\DashboardService;
use App\Support\BookingStatus;
use App\Support\DateHelper;
use App\Support\Role;
use Core\Auth;
use Core\Controller;
use Core\Exceptions\BookingException;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Core\Validator;

class AgendaController extends Controller
{
    use ResolvesBarber;

    public function index(): Response
    {
        $barber       = $this->currentBarber();
        $date         = (string) ($this->request->input('date') ?: today());
        $availability = new AvailabilityService();
        $timeline     = $availability->dayTimeline((int) $barber['id'], $date);
        $notes        = new CustomerNote();

        // Nota técnica del cliente para cada cita (spec §18).
        foreach ($timeline as $index => $item) {
            if ($item['type'] === 'booking' && $item['booking'] !== null) {
                $timeline[$index]['note'] = $notes->lastServiceNote((int) $item['booking']['customer_id']);
            }
        }

        return $this->view('barber.agenda.index', [
            'title'    => 'Mi agenda',
            'active'   => 'agenda',
            'barber'   => $barber,
            'date'     => $date,
            'dateLabel' => DateHelper::isToday($date) ? 'HOY' : mb_strtoupper(DateHelper::longEs($date, false, true)),
            'timeline' => $timeline,
            'stats'    => (new DashboardService())->barberSummary((int) $barber['id'], $date),
            'prev'     => DateHelper::make($date)->modify('-1 day')->format('Y-m-d'),
            'next'     => DateHelper::make($date)->modify('+1 day')->format('Y-m-d'),
            'week'     => $this->weekStrip($date, (int) $barber['id']),
        ]);
    }

    /** Cambia el estado de una cita propia. */
    public function changeStatus(string $id): Response
    {
        $barber  = $this->currentBarber();
        $booking = $this->ownBooking((int) $id, (int) $barber['id']);
        $status  = (string) $this->request->input('status');

        // El barbero sólo gestiona el flujo de atención, nunca cancela.
        $allowed = [
            BookingStatus::CHECKED_IN,
            BookingStatus::IN_PROGRESS,
            BookingStatus::COMPLETED,
            BookingStatus::NO_SHOW,
        ];

        if (!in_array($status, $allowed, true)) {
            return $this->respond(false, 'Esa acción no está disponible desde el panel del barbero.');
        }

        try {
            (new BookingService())->changeStatus((int) $id, $status, Auth::id());
        } catch (BookingException $e) {
            return $this->respond(false, $e->getMessage());
        }

        return $this->respond(true, match ($status) {
            BookingStatus::CHECKED_IN  => 'Llegada confirmada.',
            BookingStatus::IN_PROGRESS => 'Servicio iniciado.',
            BookingStatus::COMPLETED   => 'Servicio finalizado.',
            BookingStatus::NO_SHOW     => 'Marcado como no asistió.',
            default                    => 'Estado actualizado.',
        }, '/barbero/agenda?date=' . $booking['booking_date']);
    }

    /** Nota técnica del corte (spec §18, §32). */
    public function addNote(string $id): Response
    {
        $barber  = $this->currentBarber();
        $booking = $this->ownBooking((int) $id, (int) $barber['id']);

        $validator = new Validator($this->request->all(), ['note' => 'required|min:3|max:1000']);

        if ($validator->fails()) {
            return $this->respond(false, 'Escribe la nota antes de guardar.');
        }

        (new CustomerService())->addNote(
            (int) $booking['customer_id'],
            (string) $this->request->input('note'),
            CustomerNote::TYPE_SERVICE,
            Auth::id(),
            (int) $id,
            $this->request->boolean('pinned')
        );

        return $this->respond(true, 'Nota guardada.', '/barbero/agenda?date=' . $booking['booking_date']);
    }

    public function schedule(): Response
    {
        $barber = $this->currentBarber();

        return $this->view('barber.schedule', [
            'title'  => 'Mi horario',
            'active' => 'schedule',
            'barber' => $barber,
            'week'   => (new BarberSchedule())->weekFor((int) $barber['id']),
            'days'   => DateHelper::DAYS,
        ]);
    }

    public function blocks(): Response
    {
        $barber = $this->currentBarber();

        return $this->view('barber.blocks', [
            'title'  => 'Mis bloqueos',
            'active' => 'blocks',
            'barber' => $barber,
            'blocks' => (new BlockedTime())->upcoming(30, (int) $barber['id']),
            'types'  => BlockedTime::TYPES,
        ]);
    }

    /** El barbero puede bloquear su propia agenda (almuerzo, trámite). */
    public function storeBlock(): Response
    {
        $barber = $this->currentBarber();

        $validator = new Validator($this->request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|time',
            'end_date'   => 'required|date_format:Y-m-d',
            'end_time'   => 'required|time',
            'type'       => 'required|in:' . implode(',', array_keys(BlockedTime::TYPES)),
            'reason'     => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $start = $this->request->input('start_date') . ' ' . substr((string) $this->request->input('start_time'), 0, 5) . ':00';
        $end   = $this->request->input('end_date') . ' ' . substr((string) $this->request->input('end_time'), 0, 5) . ':00';

        if ($start >= $end) {
            Session::flash('error', 'El término debe ser posterior al inicio.');

            return $this->back();
        }

        $model     = new BlockedTime();
        $conflicts = $model->conflictingBookings((int) $barber['id'], $start, $end);

        if ($conflicts !== []) {
            Session::flash('error', sprintf(
                'Tienes %d reserva(s) en ese rango (%s). Avisa a recepción para reprogramarlas.',
                count($conflicts),
                implode(', ', array_column($conflicts, 'public_code'))
            ));

            return $this->back();
        }

        $id = $model->create([
            'branch_id'      => (int) $barber['branch_id'],
            'barber_id'      => (int) $barber['id'],
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'type'           => (string) $this->request->input('type'),
            'reason'         => $this->request->input('reason') ?: null,
            'created_by'     => Auth::id(),
        ]);

        ActivityLogger::log('blocked.created', 'blocked_time', $id, $barber['display_name'] . ' bloqueó su horario');

        return $this->redirectWith('/barbero/bloqueos', 'Bloqueo creado.');
    }

    public function deleteBlock(string $id): Response
    {
        $barber = $this->currentBarber();
        $model  = new BlockedTime();
        $block  = $model->findOrFail((int) $id);

        // Sólo puede borrar sus propios bloqueos, no los de la sucursal.
        $this->authorize(
            (int) $block['barber_id'] === (int) $barber['id'],
            'Sólo puedes eliminar tus propios bloqueos.'
        );

        $model->delete((int) $id);
        ActivityLogger::log('blocked.deleted', 'blocked_time', (int) $id, $barber['display_name'] . ' eliminó un bloqueo');

        return $this->redirectWith('/barbero/bloqueos', 'Bloqueo eliminado.');
    }

    // -----------------------------------------------------------------
    //  Internos
    // -----------------------------------------------------------------

    protected function ownBooking(int $bookingId, int $barberId): array
    {
        $booking = (new Booking())->findFull($bookingId);

        if ($booking === null) {
            throw HttpException::notFound('Reserva no encontrada');
        }

        $this->authorize(
            (int) $booking['barber_id'] === $barberId || Auth::hasRole(Role::ADMIN, Role::SUPER_ADMIN),
            'Esa reserva no pertenece a tu agenda.'
        );

        return $booking;
    }

    /** Tira de 7 días con el conteo de citas, para navegar rápido. */
    private function weekStrip(string $date, int $barberId): array
    {
        $bookings = new Booking();
        $strip    = [];

        foreach (range(0, 6) as $offset) {
            $day = DateHelper::make($date)->modify(($offset === 0 ? '' : '+') . $offset . ' days')->format('Y-m-d');

            $strip[] = [
                'date'     => $day,
                'label'    => DateHelper::DAYS_SHORT[DateHelper::weekday($day)],
                'day'      => (int) DateHelper::make($day)->format('j'),
                'count'    => count(array_filter(
                    $bookings->agendaFor($barberId, $day),
                    static fn (array $b): bool => !in_array($b['status'], ['cancelled', 'no_show'], true)
                )),
                'is_today' => DateHelper::isToday($day),
                'active'   => $day === $date,
            ];
        }

        return $strip;
    }

    private function respond(bool $ok, string $message, string $redirect = '/barbero/agenda'): Response
    {
        if ($this->request->expectsJson()) {
            return $ok ? $this->success($message) : $this->fail($message, [], 409);
        }

        Session::flash($ok ? 'success' : 'error', $message);

        return $this->redirect($redirect);
    }
}
