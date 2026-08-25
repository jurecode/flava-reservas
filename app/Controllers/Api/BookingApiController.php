<?php
/**
 * Ruta: /app/Controllers/Api/BookingApiController.php
 *
 * API pública del booking. La consume el frontend con fetch y es la base de la
 * futura API v1 para la app móvil (spec §72, §73).
 * Formato uniforme: {success, message, data|errors} (spec §62).
 */

namespace App\Controllers\Api;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\DateHelper;
use App\Support\Money;
use App\Support\PaymentMethod;
use Core\Controller;
use Core\Exceptions\BookingException;
use Core\Response;
use Core\Validator;

class BookingApiController extends Controller
{
    private AvailabilityService $availability;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->availability = new AvailabilityService();
    }

    /** GET /api/v1/services */
    public function services(): Response
    {
        $services = array_map(static fn (array $service): array => [
            'id'            => (int) $service['id'],
            'name'          => $service['name'],
            'slug'          => $service['slug'],
            'description'   => $service['description'],
            'price'         => (float) $service['price'],
            'price_label'   => Money::format($service['price']),
            'duration'      => (int) $service['duration_minutes'],
            'category'      => $service['category_name'] ?? null,
            'image'         => upload_url($service['image']),
            'featured'      => (bool) $service['is_featured'],
        ], (new Service())->bookable());

        return $this->success('', $services);
    }

    /** GET /api/v1/barbers?service_id=1 */
    public function barbers(): Response
    {
        $serviceId = $this->request->integer('service_id');
        $model     = new Barber();

        $barbers = $serviceId !== null
            ? $model->availableForService($serviceId)
            : $model->publicList(Branch::defaultId());

        $service = $serviceId !== null ? (new Service())->find($serviceId) : null;
        $sm      = new Service();

        $data = array_map(static function (array $barber) use ($service, $sm): array {
            $effective = $service !== null ? $sm->effectiveFor($service, (int) $barber['id']) : null;

            return [
                'id'         => (int) $barber['id'],
                'name'       => $barber['display_name'],
                'slug'       => $barber['slug'],
                'specialty'  => $barber['specialty'],
                'bio'        => $barber['bio'],
                'photo'      => upload_url($barber['photo']),
                'color'      => $barber['color'],
                'price'      => $effective ? (float) $effective['price'] : null,
                'duration'   => $effective ? (int) $effective['duration'] : null,
            ];
        }, $barbers);

        return $this->success('', $data);
    }

    /** GET /api/v1/availability/dates?service_id=1&barber_id=2&days=14 */
    public function dates(): Response
    {
        $validator = new Validator($this->request->all(), [
            'service_id' => 'required|integer|exists:services,id',
        ]);

        if ($validator->fails()) {
            return $this->fail('Parámetros inválidos', $validator->errors());
        }

        $barberId = $this->request->input('barber_id');
        $barberId = ($barberId === null || $barberId === '' || $barberId === 'any') ? null : (int) $barberId;

        return $this->success('', $this->availability->availableDates(
            (int) $this->request->input('service_id'),
            $barberId,
            min(30, max(1, $this->request->integer('days', 14)))
        ));
    }

    /** GET /api/v1/availability/slots?service_id=1&barber_id=2&date=2026-08-28 */
    public function slots(): Response
    {
        $validator = new Validator($this->request->all(), [
            'service_id' => 'required|integer|exists:services,id',
            'date'       => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->fail('Parámetros inválidos', $validator->errors());
        }

        $serviceId = (int) $this->request->input('service_id');
        $date      = (string) $this->request->input('date');
        $barberRaw = $this->request->input('barber_id');
        $barberId  = ($barberRaw === null || $barberRaw === '' || $barberRaw === 'any') ? null : (int) $barberRaw;

        $slots = $barberId !== null
            ? $this->availability->slotsFor($barberId, $serviceId, $date)
            : $this->availability->slotsForAnyBarber($serviceId, $date);

        return $this->success('', [
            'date'       => $date,
            'date_label' => DateHelper::longEs($date, false, true),
            'slots'      => $slots,
            'count'      => count($slots),
        ]);
    }

    /** POST /api/v1/bookings */
    public function store(): Response
    {
        $requireRut = (bool) setting('require_rut', true);

        $validator = new Validator($this->request->all(), [
            'service_id'     => 'required|integer|exists:services,id',
            'booking_date'   => 'required|date_format:Y-m-d',
            'start_time'     => 'required|time',
            'first_name'     => 'required|min:2|max:80',
            'last_name'      => 'required|min:2|max:80',
            'email'          => 'required|email|max:150',
            'phone'          => 'required|phone',
            'rut'            => $requireRut ? 'required|rut' : 'nullable|rut',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::forCheckout()),
            'customer_notes' => 'nullable|max:500',
        ]);

        if ($validator->fails()) {
            return $this->fail('Revisa los datos ingresados', $validator->errors());
        }

        $data = $validator->validated();

        try {
            $booking = (new BookingService())->create([
                'service_id'     => (int) $data['service_id'],
                'barber_id'      => $this->request->input('barber_id'),
                'booking_date'   => $data['booking_date'],
                'start_time'     => $data['start_time'],
                'branch_id'      => Branch::defaultId(),
                'source'         => BookingSource::WEBSITE,
                'payment_method' => $data['payment_method'],
                'customer_notes' => $data['customer_notes'] ?? null,
                'customer'       => [
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'],
                    'rut'        => $data['rut'] ?? null,
                    'email'      => $data['email'],
                    'phone'      => $data['phone'],
                ],
            ]);
        } catch (BookingException $e) {
            return $this->fail($e->getMessage(), [], 409);
        }

        $token = (string) ((new Booking())->find((int) $booking['id'])['token'] ?? '');

        return $this->success('Reserva creada correctamente', [
            'code'        => $booking['public_code'],
            'status'      => $booking['status'],
            'date'        => $booking['booking_date'],
            'time'        => substr((string) $booking['start_time'], 0, 5),
            'barber'      => $booking['barber_name'],
            'service'     => $booking['service_name'],
            'total'       => (float) $booking['total'],
            'total_label' => Money::format($booking['total']),
            'manage_url'  => url('reserva/' . $booking['public_code']) . '?token=' . $token,
        ], 201);
    }

    /**
     * GET /api/v1/bookings/{code}?token=...
     * Exige token: nunca se expone información con sólo el código (spec §94).
     */
    public function show(string $code): Response
    {
        $token = (string) ($this->request->query('token') ?? '');

        if ($token === '') {
            return $this->fail('Se requiere el token de la reserva', [], 403);
        }

        $booking = (new Booking())->findByCodeAndToken($code, $token);

        if ($booking === null) {
            return $this->fail('Reserva no encontrada', [], 404);
        }

        return $this->success('', [
            'code'         => $booking['public_code'],
            'status'       => $booking['status'],
            'status_label' => BookingStatus::label((string) $booking['status']),
            'service'      => $booking['service_name'],
            'barber'       => $booking['barber_name'],
            'date'         => $booking['booking_date'],
            'date_label'   => DateHelper::longEs((string) $booking['booking_date'], true, true),
            'time'         => substr((string) $booking['start_time'], 0, 5),
            'end_time'     => substr((string) $booking['end_time'], 0, 5),
            'duration'     => (int) $booking['duration_minutes'],
            'total'        => (float) $booking['total'],
            'total_label'  => Money::format($booking['total']),
            'payment_status' => $booking['payment_status'],
        ]);
    }
}
