<?php
/**
 * Ruta: /app/Controllers/BookingController.php
 *
 * Flujo público de reserva (spec §23): servicio → barbero → fecha → hora →
 * checkout. El cliente NO se registra; la selección se guarda en sesión y el
 * checkout es tipo e-commerce.
 */

namespace App\Controllers;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\SettingService;
use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\DateHelper;
use App\Support\PaymentMethod;
use Core\Controller;
use Core\Exceptions\BookingException;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Core\Validator;

class BookingController extends Controller
{
    private const SESSION_KEY = '_booking_draft';

    private AvailabilityService $availability;
    private BookingService $bookings;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);

        $this->availability = new AvailabilityService();
        $this->bookings     = new BookingService();
    }

    // =================================================================
    //  PASO 1 — SERVICIO
    // =================================================================
    public function start(): Response
    {
        // Permite entrar directo con ?servicio=corte-fade o ?barbero=sebastian
        $draft = $this->draft();

        if ($slug = $this->request->query('servicio')) {
            $service = (new Service())->findBySlug((string) $slug);

            if ($service !== null && (int) $service['status'] === 1) {
                $draft['service_id'] = (int) $service['id'];
            }
        }

        if ($slug = $this->request->query('barbero')) {
            $barber = (new Barber())->findBySlug((string) $slug);

            if ($barber !== null && (int) $barber['status'] === 1) {
                $draft['barber_id'] = (int) $barber['id'];
            }
        }

        $this->saveDraft($draft);

        return $this->view('booking.service', [
            'title'       => 'Reservar hora | ' . setting('business_name', config('app.name')),
            'description' => 'Reserva tu hora en pocos segundos: elige servicio, barbero, fecha y horario.',
            'business'    => SettingService::business(),
            'services'    => (new Service())->bookable(),
            'draft'       => $draft,
            'step'        => 1,
        ]);
    }

    // =================================================================
    //  PASO 2 — BARBERO
    // =================================================================
    public function barber(): Response
    {
        $draft     = $this->draft();
        $serviceId = $this->request->integer('service_id') ?? ($draft['service_id'] ?? null);

        if ($serviceId === null) {
            return $this->redirect('/reservar');
        }

        $service = (new Service())->find($serviceId);

        if ($service === null || (int) $service['status'] !== 1) {
            Session::flash('error', 'El servicio seleccionado ya no está disponible.');

            return $this->redirect('/reservar');
        }

        $draft['service_id'] = $serviceId;
        $this->saveDraft($draft);

        $barbers = (new Barber())->availableForService($serviceId);
        $model   = new Service();

        foreach ($barbers as $index => $barber) {
            $effective                     = $model->effectiveFor($service, (int) $barber['id']);
            $barbers[$index]['price']      = $effective['price'];
            $barbers[$index]['duration']   = $effective['duration'];
            $barbers[$index]['next_free']  = $this->nextAvailableLabel((int) $barber['id'], $serviceId);
        }

        return $this->view('booking.barber', [
            'title'      => 'Elige tu barbero | ' . setting('business_name', config('app.name')),
            'business'   => SettingService::business(),
            'service'    => $service,
            'barbers'    => $barbers,
            'allow_any'  => (bool) setting('allow_any_barber', true) && count($barbers) > 1,
            'draft'      => $draft,
            'step'       => 2,
        ]);
    }

    // =================================================================
    //  PASOS 3 y 4 — FECHA Y HORA (una sola pantalla, mobile first)
    // =================================================================
    public function date(): Response
    {
        $draft     = $this->draft();
        $serviceId = $this->request->integer('service_id') ?? ($draft['service_id'] ?? null);
        $barberRaw = $this->request->input('barber_id') ?? ($draft['barber_id'] ?? null);

        if ($serviceId === null) {
            return $this->redirect('/reservar');
        }

        $service = (new Service())->find($serviceId);

        if ($service === null) {
            return $this->redirect('/reservar');
        }

        $barberId = ($barberRaw === null || $barberRaw === '' || $barberRaw === 'any') ? null : (int) $barberRaw;

        $draft['service_id'] = $serviceId;
        $draft['barber_id']  = $barberId ?? 'any';
        $this->saveDraft($draft);

        $dates    = $this->availability->availableDates($serviceId, $barberId, 14);
        $selected = (string) ($this->request->input('date') ?? $this->firstAvailableDate($dates));

        $slots = $barberId !== null
            ? $this->availability->slotsFor($barberId, $serviceId, $selected)
            : $this->availability->slotsForAnyBarber($serviceId, $selected);

        return $this->view('booking.date', [
            'title'     => 'Elige fecha y hora | ' . setting('business_name', config('app.name')),
            'business'  => SettingService::business(),
            'service'   => $service,
            'barber'    => $barberId !== null ? (new Barber())->find($barberId) : null,
            'barber_id' => $barberId ?? 'any',
            'dates'     => $dates,
            'selected'  => $selected,
            'slots'     => $slots,
            'draft'     => $draft,
            'step'      => 3,
        ]);
    }

    // =================================================================
    //  CHECKOUT (spec §11, §66)
    // =================================================================
    public function checkout(): Response
    {
        $draft = $this->draft();

        $serviceId = $this->request->integer('service_id') ?? ($draft['service_id'] ?? null);
        $barberRaw = $this->request->input('barber_id') ?? ($draft['barber_id'] ?? 'any');
        $date      = (string) ($this->request->input('date') ?? ($draft['date'] ?? ''));
        $time      = substr((string) ($this->request->input('time') ?? ($draft['time'] ?? '')), 0, 5);

        if ($serviceId === null || $date === '' || $time === '') {
            Session::flash('error', 'Completa los pasos anteriores para continuar.');

            return $this->redirect('/reservar');
        }

        $service = (new Service())->find($serviceId);

        if ($service === null) {
            return $this->redirect('/reservar');
        }

        $barberId = ($barberRaw === 'any' || $barberRaw === '' || $barberRaw === null) ? null : (int) $barberRaw;

        // Si el cliente eligió "cualquier barbero", ya se resuelve aquí para
        // poder mostrarle con quién será atendido antes de confirmar.
        if ($barberId === null) {
            $barberId = $this->availability->firstAvailableBarber($serviceId, $date, $time);

            if ($barberId === null) {
                Session::flash('error', 'Ese horario ya no está disponible. Elige otro.');

                return $this->redirect('/reservar/fecha?service_id=' . $serviceId);
            }
        }

        $check = $this->availability->validateSlot($barberId, $serviceId, $date, $time);

        if (!$check['ok']) {
            Session::flash('error', (string) $check['reason']);

            return $this->redirect('/reservar/fecha?service_id=' . $serviceId . '&barber_id=' . $barberId);
        }

        $draft = array_merge($draft, [
            'service_id' => $serviceId,
            'barber_id'  => $barberId,
            'date'       => $date,
            'time'       => $time,
        ]);
        $this->saveDraft($draft);

        return $this->view('booking.checkout', [
            'title'      => 'Confirma tu reserva | ' . setting('business_name', config('app.name')),
            'business'   => SettingService::business(),
            'service'    => $service,
            'barber'     => (new Barber())->find($barberId),
            'date'       => $date,
            'time'       => $time,
            'end_time'   => $check['end_time'],
            'duration'   => $check['duration'],
            'price'      => $check['price'],
            'methods'    => PaymentMethod::forCheckout(),
            'require_rut' => (bool) setting('require_rut', true),
            'policy'     => setting('booking_policy', ''),
            'step'       => 4,
        ]);
    }

    /** Crea la reserva. Toda la validación crítica ocurre en el backend. */
    public function store(): Response
    {
        $this->verifyCsrf();

        $requireRut = (bool) setting('require_rut', true);

        $rules = [
            'service_id'     => 'required|integer|exists:services,id',
            'booking_date'   => 'required|date_format:Y-m-d',
            'start_time'     => 'required|time',
            'first_name'     => 'required|min:2|max:80',
            'last_name'      => 'required|min:2|max:80',
            'email'          => 'required|email|max:150',
            'phone'          => 'required|phone',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::forCheckout()),
            'rut'            => ($requireRut ? 'required|rut' : 'nullable|rut'),
            'customer_notes' => 'nullable|max:500',
            'accept_policy'  => 'required',
        ];

        $validator = new Validator($this->request->all(), $rules, [
            'accept_policy' => 'Debes aceptar las políticas de reserva.',
        ]);

        if ($validator->fails()) {
            if ($this->request->expectsJson()) {
                return $this->fail('Revisa los datos ingresados', $validator->errors());
            }

            return $this->backWithErrors($validator->errors());
        }

        $data = $validator->validated();

        try {
            $booking = $this->bookings->create([
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
            if ($this->request->expectsJson()) {
                return $this->fail($e->getMessage(), [], 409);
            }

            Session::flash('error', $e->getMessage());

            return $this->redirect('/reservar/fecha?service_id=' . (int) $data['service_id']);
        }

        $this->clearDraft();

        $token       = (string) ((new Booking())->find((int) $booking['id'])['token'] ?? '');
        $confirmUrl  = '/reserva/' . $booking['public_code'] . '?token=' . $token;

        // Guarda el token en sesión para que el cliente vea su reserva sin la
        // URL larga si vuelve atrás en el navegador.
        Session::put('_last_booking', ['code' => $booking['public_code'], 'token' => $token]);

        if ($this->request->expectsJson()) {
            return $this->success('Reserva creada correctamente', [
                'code'        => $booking['public_code'],
                'redirect'    => url(ltrim($confirmUrl, '/')),
            ]);
        }

        return $this->redirect($confirmUrl);
    }

    // =================================================================
    //  GESTIÓN SIN CUENTA (código + token — spec §29)
    // =================================================================
    public function show(string $code): Response
    {
        $booking = $this->authorizeBooking($code);

        return $this->view('booking.confirmation', [
            'title'      => 'Reserva ' . $booking['public_code'] . ' | ' . setting('business_name', config('app.name')),
            'business'   => SettingService::business(),
            'booking'    => $booking,
            'token'      => $this->tokenFor($code),
            'can_cancel' => $this->canManage($booking, (int) setting('cancel_limit_hours', 2)),
            'can_move'   => $this->canManage($booking, (int) setting('reschedule_limit_hours', 2)),
            'policy'     => setting('booking_policy', ''),
        ]);
    }

    public function lookupForm(): Response
    {
        return $this->view('booking.lookup', [
            'title'    => 'Consultar mi reserva | ' . setting('business_name', config('app.name')),
            'business' => SettingService::business(),
        ]);
    }

    /**
     * Búsqueda por código + email: no expone datos si el email no coincide.
     * El enlace con token sigue siendo el mecanismo principal (spec §94).
     */
    public function lookup(): Response
    {
        $this->verifyCsrf();

        $code  = strtoupper(trim((string) $this->request->input('code')));
        $email = mb_strtolower(trim((string) $this->request->input('email')));

        $booking = (new Booking())->findByCode($code);

        if ($booking === null || mb_strtolower((string) $booking['customer_email']) !== $email) {
            Session::flash('error', 'No encontramos una reserva con esos datos.');
            Session::flashInput(['code' => $code]);

            return $this->back('/mi-reserva');
        }

        $token = (string) ((new Booking())->find((int) $booking['id'])['token'] ?? '');

        return $this->redirect('/reserva/' . $booking['public_code'] . '?token=' . $token);
    }

    public function rescheduleForm(string $code): Response
    {
        $booking = $this->authorizeBooking($code);

        if (!$this->canManage($booking, (int) setting('reschedule_limit_hours', 2))) {
            Session::flash('error', $this->policyMessage('reprogramar', (int) setting('reschedule_limit_hours', 2)));

            return $this->redirect('/reserva/' . $code . '?token=' . $this->tokenFor($code));
        }

        $serviceId = (int) $booking['service_id'];
        $barberId  = (int) $booking['barber_id'];
        $dates     = $this->availability->availableDates($serviceId, $barberId, 14);
        $selected  = (string) ($this->request->input('date') ?? $this->firstAvailableDate($dates));

        return $this->view('booking.reschedule', [
            'title'    => 'Reprogramar reserva | ' . setting('business_name', config('app.name')),
            'business' => SettingService::business(),
            'booking'  => $booking,
            'token'    => $this->tokenFor($code),
            'dates'    => $dates,
            'selected' => $selected,
            'slots'    => $this->availability->slotsFor($barberId, $serviceId, $selected, false, (int) $booking['id']),
        ]);
    }

    public function reschedule(string $code): Response
    {
        $this->verifyCsrf();

        $booking = $this->authorizeBooking($code);

        $validator = new Validator($this->request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|time',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        try {
            $this->bookings->reschedule(
                (int) $booking['id'],
                (string) $this->request->input('date'),
                (string) $this->request->input('time')
            );
        } catch (BookingException $e) {
            Session::flash('error', $e->getMessage());

            return $this->back();
        }

        Session::flash('success', '¡Listo! Tu reserva quedó reprogramada.');

        return $this->redirect('/reserva/' . $code . '?token=' . $this->tokenFor($code));
    }

    public function cancelForm(string $code): Response
    {
        $booking = $this->authorizeBooking($code);

        return $this->view('booking.cancel', [
            'title'    => 'Cancelar reserva | ' . setting('business_name', config('app.name')),
            'business' => SettingService::business(),
            'booking'  => $booking,
            'token'    => $this->tokenFor($code),
            'allowed'  => $this->canManage($booking, (int) setting('cancel_limit_hours', 2)),
            'message'  => $this->policyMessage('cancelar', (int) setting('cancel_limit_hours', 2)),
        ]);
    }

    public function cancel(string $code): Response
    {
        $this->verifyCsrf();

        $booking = $this->authorizeBooking($code);

        try {
            $this->bookings->cancel(
                (int) $booking['id'],
                (string) ($this->request->input('reason') ?: 'Cancelada por el cliente')
            );
        } catch (BookingException $e) {
            Session::flash('error', $e->getMessage());

            return $this->back();
        }

        Session::flash('success', 'Tu reserva fue cancelada. ¡Te esperamos en otra ocasión!');

        return $this->redirect('/reserva/' . $code . '?token=' . $this->tokenFor($code));
    }

    /** Archivo .ics para agregar la reserva al calendario (spec §28). */
    public function calendarFile(string $code): Response
    {
        $booking  = $this->authorizeBooking($code);
        $business = setting('business_name', config('app.name'));
        $start    = DateHelper::make($booking['booking_date'] . ' ' . $booking['start_time']);
        $end      = DateHelper::make($booking['booking_date'] . ' ' . $booking['end_time']);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Flava Studio//Reservas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $booking['public_code'] . '@flava.cl',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTEND:' . $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'SUMMARY:' . $this->icsEscape($booking['service_name'] . ' · ' . $business),
            'DESCRIPTION:' . $this->icsEscape(
                'Barbero: ' . $booking['barber_name'] . '\nCódigo: ' . $booking['public_code']
            ),
            'LOCATION:' . $this->icsEscape((string) setting('business_address', '')),
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT2H',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . $this->icsEscape('Tu hora en ' . $business . ' es en 2 horas'),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return Response::make(implode("\r\n", $lines), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="flava-' . $booking['public_code'] . '.ics"',
        ]);
    }

    // =================================================================
    //  Internos
    // =================================================================

    /** Exige token válido para ver o modificar una reserva (spec §94). */
    private function authorizeBooking(string $code): array
    {
        $token = $this->tokenFor($code);

        if ($token === '') {
            throw HttpException::forbidden('Necesitas el enlace privado de tu reserva para verla.');
        }

        $booking = (new Booking())->findByCodeAndToken($code, $token);

        if ($booking === null) {
            throw HttpException::notFound('No encontramos esa reserva o el enlace no es válido.');
        }

        return $booking;
    }

    /** El token puede venir por URL o de la sesión de la reserva recién creada. */
    private function tokenFor(string $code): string
    {
        $fromQuery = (string) ($this->request->query('token') ?? $this->request->input('token') ?? '');

        if ($fromQuery !== '') {
            return $fromQuery;
        }

        $last = Session::get('_last_booking');

        if (is_array($last) && ($last['code'] ?? '') === strtoupper($code)) {
            return (string) $last['token'];
        }

        return '';
    }

    private function canManage(array $booking, int $limitHours): bool
    {
        if (!BookingStatus::isCancellable((string) $booking['status'])) {
            return false;
        }

        if ($limitHours <= 0) {
            return true;
        }

        $start = DateHelper::make($booking['booking_date'] . ' ' . $booking['start_time']);

        return $start > DateHelper::make()->modify("+{$limitHours} hours");
    }

    private function policyMessage(string $action, int $hours): string
    {
        return sprintf(
            'No es posible %s con menos de %d %s de anticipación. Escríbenos por WhatsApp y te ayudamos.',
            $action,
            $hours,
            $hours === 1 ? 'hora' : 'horas'
        );
    }

    private function nextAvailableLabel(int $barberId, int $serviceId): ?string
    {
        foreach ($this->availability->availableDates($serviceId, $barberId, 7) as $day) {
            if ($day['available']) {
                $slots = $this->availability->slotsFor($barberId, $serviceId, $day['date']);

                if ($slots !== []) {
                    return DateHelper::friendly($day['date']) . ' · ' . $slots[0]['time'];
                }
            }
        }

        return null;
    }

    private function firstAvailableDate(array $dates): string
    {
        foreach ($dates as $day) {
            if ($day['available']) {
                return $day['date'];
            }
        }

        return $dates[0]['date'] ?? today();
    }

    /** Borrador de la reserva en curso (sólo ids, nunca datos personales). */
    private function draft(): array
    {
        $draft = Session::get(self::SESSION_KEY, []);

        return is_array($draft) ? $draft : [];
    }

    private function saveDraft(array $draft): void
    {
        Session::put(self::SESSION_KEY, $draft);
    }

    private function clearDraft(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private function icsEscape(string $text): string
    {
        return str_replace([',', ';'], ['\,', '\;'], $text);
    }
}
