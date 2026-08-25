<?php
/**
 * Ruta: /app/Controllers/Api/AdminApiController.php
 * Endpoints internos que alimentan los paneles (calendario, buscador, slots).
 * Todos exigen sesión válida y respetan el rol del usuario.
 */

namespace App\Controllers\Api;

use App\Models\Barber;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\DashboardService;
use App\Support\BookingStatus;
use App\Support\DateHelper;
use App\Support\Money;
use App\Support\Role;
use App\Support\Rut;
use App\Support\Str;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Validator;

class AdminApiController extends Controller
{
    /** GET /api/v1/admin/availability/slots — motor compartido con el booking web. */
    public function slots(): Response
    {
        $validator = new Validator($this->request->all(), [
            'service_id' => 'required|integer|exists:services,id',
            'barber_id'  => 'required|integer|exists:barbers,id',
            'date'       => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->fail('Parámetros inválidos', $validator->errors());
        }

        $availability = new AvailabilityService();
        $exclude      = $this->request->integer('exclude_booking_id');

        // internal = true: recepción puede agendar sin la anticipación mínima.
        $slots = $availability->slotsFor(
            (int) $this->request->input('barber_id'),
            (int) $this->request->input('service_id'),
            (string) $this->request->input('date'),
            true,
            $exclude
        );

        return $this->success('', ['slots' => $slots, 'count' => count($slots)]);
    }

    /** GET /api/v1/admin/customers/search?q=Rodrigo — buscador de recepción (spec §90). */
    public function searchCustomers(): Response
    {
        $term = trim((string) $this->request->input('q'));

        if (mb_strlen($term) < 2) {
            return $this->success('', []);
        }

        $customers = (new Customer())->search($term, 15);

        $data = array_map(static fn (array $customer): array => [
            'id'         => (int) $customer['id'],
            'name'       => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'rut'        => $customer['rut'] ? Rut::format($customer['rut']) : null,
            'phone'      => Str::phoneDisplay($customer['phone']),
            'email'      => $customer['email'],
            'visits'     => (int) $customer['completed_bookings'],
            'no_shows'   => (int) $customer['no_show_count'],
            'last_visit' => $customer['last_visit_at'],
        ], $customers);

        return $this->success('', $data);
    }

    /** GET /api/v1/admin/calendar/events?start=...&end=... — alimenta FullCalendar. */
    public function calendarEvents(): Response
    {
        $start = substr((string) ($this->request->input('start') ?: today()), 0, 10);
        $end   = substr((string) ($this->request->input('end') ?: today()), 0, 10);

        $filters = [];

        // El barbero sólo puede ver su propia agenda.
        if (Auth::is(Role::BARBER)) {
            $filters['barber_id'] = Auth::barberId();
        } elseif ($this->request->filled('barber_id')) {
            $filters['barber_id'] = $this->request->integer('barber_id');
        }

        $events = [];

        foreach ((new Booking())->forRange($start, $end, $filters) as $booking) {
            $events[] = [
                'id'              => 'booking-' . $booking['id'],
                'title'           => sprintf(
                    '%s · %s',
                    trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name']),
                    $booking['service_name']
                ),
                'start'           => $booking['booking_date'] . 'T' . $booking['start_time'],
                'end'             => $booking['booking_date'] . 'T' . $booking['end_time'],
                'backgroundColor' => BookingStatus::color((string) $booking['status']),
                'borderColor'     => $booking['barber_color'] ?: '#FFC400',
                'textColor'       => in_array($booking['status'], ['pending', 'confirmed'], true) ? '#181818' : '#FFFDF5',
                'extendedProps'   => [
                    'type'         => 'booking',
                    'bookingId'    => (int) $booking['id'],
                    'code'         => $booking['public_code'],
                    'barber'       => $booking['barber_name'],
                    'barberId'     => (int) $booking['barber_id'],
                    'status'       => $booking['status'],
                    'statusLabel'  => BookingStatus::label((string) $booking['status']),
                    'paymentStatus' => $booking['payment_status'],
                    'phone'        => $booking['customer_phone'],
                    'total'        => Money::format($booking['total']),
                ],
            ];
        }

        foreach ((new BlockedTime())->forRange($start, $end, $filters['barber_id'] ?? null, Branch::defaultId()) as $block) {
            $events[] = [
                'id'              => 'block-' . $block['id'],
                'title'           => BlockedTime::typeLabel((string) $block['type']) . ($block['barber_name'] ? ' · ' . $block['barber_name'] : ''),
                'start'           => str_replace(' ', 'T', (string) $block['start_datetime']),
                'end'             => str_replace(' ', 'T', (string) $block['end_datetime']),
                'backgroundColor' => '#DEE2E6',
                'borderColor'     => '#ADB5BD',
                'textColor'       => '#495057',
                'display'         => 'block',
                'extendedProps'   => [
                    'type'    => 'block',
                    'blockId' => (int) $block['id'],
                    'reason'  => $block['reason'],
                ],
            ];
        }

        return $this->success('', $events);
    }

    /** GET /api/v1/admin/services/{id}/barbers */
    public function serviceBarbers(string $id): Response
    {
        $service = (new Service())->find((int) $id);

        if ($service === null) {
            return $this->fail('Servicio no encontrado', [], 404);
        }

        $sm   = new Service();
        $data = array_map(static function (array $barber) use ($service, $sm): array {
            $effective = $sm->effectiveFor($service, (int) $barber['id']);

            return [
                'id'       => (int) $barber['id'],
                'name'     => $barber['display_name'],
                'color'    => $barber['color'],
                'price'    => (float) $effective['price'],
                'duration' => (int) $effective['duration'],
            ];
        }, (new Barber())->availableForService((int) $id, false));

        return $this->success('', [
            'service'  => ['id' => (int) $service['id'], 'name' => $service['name'], 'duration' => (int) $service['duration_minutes']],
            'barbers'  => $data,
        ]);
    }

    /** GET /api/v1/admin/bookings/{id} — detalle para modales rápidos. */
    public function booking(string $id): Response
    {
        $booking = (new Booking())->findFull((int) $id);

        if ($booking === null) {
            return $this->fail('Reserva no encontrada', [], 404);
        }

        // El barbero sólo accede a sus propias reservas.
        if (Auth::is(Role::BARBER) && (int) $booking['barber_id'] !== Auth::barberId()) {
            return $this->fail('No tienes acceso a esta reserva', [], 403);
        }

        $payload = [
            'id'           => (int) $booking['id'],
            'code'         => $booking['public_code'],
            'customer'     => trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name']),
            'customer_id'  => (int) $booking['customer_id'],
            'phone'        => Str::phoneDisplay($booking['customer_phone']),
            'service'      => $booking['service_name'],
            'barber'       => $booking['barber_name'],
            'date'         => $booking['booking_date'],
            'date_label'   => DateHelper::longEs((string) $booking['booking_date'], false, true),
            'time'         => substr((string) $booking['start_time'], 0, 5),
            'end_time'     => substr((string) $booking['end_time'], 0, 5),
            'status'       => $booking['status'],
            'status_label' => BookingStatus::label((string) $booking['status']),
            'total'        => Money::format($booking['total']),
            'payment_status' => $booking['payment_status'],
            'notes'        => $booking['customer_notes'],
            'next_states'  => array_map(
                static fn (string $status): array => ['value' => $status, 'label' => BookingStatus::label($status)],
                BookingStatus::nextOptions((string) $booking['status'])
            ),
        ];

        // Datos administrativos sólo para quien corresponde.
        if (!Auth::is(Role::BARBER)) {
            $payload['rut']            = $booking['customer_rut'];
            $payload['email']          = $booking['customer_email'];
            $payload['internal_notes'] = $booking['internal_notes'];
        }

        return $this->success('', $payload);
    }

    /** GET /api/v1/admin/stats/summary */
    public function summary(): Response
    {
        $dashboard = new DashboardService();

        if (Auth::is(Role::BARBER)) {
            $barberId = Auth::barberId();

            return $this->success('', $barberId !== null ? $dashboard->barberSummary($barberId) : []);
        }

        if (Auth::is(Role::RECEPTION)) {
            return $this->success('', $dashboard->receptionSummary());
        }

        return $this->success('', $dashboard->adminSummary());
    }
}
