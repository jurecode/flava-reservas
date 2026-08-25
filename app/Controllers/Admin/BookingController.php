<?php
/**
 * Ruta: /app/Controllers/Admin/BookingController.php
 *
 * Administración de reservas. La creación manual usa EXACTAMENTE el mismo
 * motor de disponibilidad que el booking web (spec §36, §71).
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\CustomerService;
use App\Services\PaymentService;
use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\PaymentMethod;
use Core\Auth;
use Core\Controller;
use Core\Exceptions\BookingException;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;
use Core\Validator;

class BookingController extends Controller
{
    protected string $panel = 'admin';
    protected string $basePath = '/admin';

    public function index(): Response
    {
        $filters = $this->request->only([
            'search', 'barber_id', 'service_id', 'status', 'payment_status',
            'source', 'date_from', 'date_to', 'customer_id', 'upcoming', 'order',
        ]);

        // Por defecto: agenda de hoy en adelante.
        if ($filters === [] || (!isset($filters['date_from']) && !isset($filters['search']) && !isset($filters['status']))) {
            $filters['date_from'] = $filters['date_from'] ?? today();
            $filters['order']     = 'asc';
        }

        $filters['branch_id'] = Branch::defaultId();

        $result = (new Booking())->paginateFiltered(
            $filters,
            max(1, $this->request->integer('page', 1)),
            25
        );

        return $this->view($this->panel . '.bookings.index', [
            'title'    => 'Reservas',
            'active'   => 'bookings',
            'result'   => $result,
            'filters'  => $filters,
            'barbers'  => (new Barber())->activeList(),
            'services' => (new Service())->activeAll(),
            'statuses' => BookingStatus::all(),
            'basePath' => $this->basePath,
        ]);
    }

    public function show(string $id): Response
    {
        $booking = (new Booking())->findFull((int) $id);

        if ($booking === null) {
            throw HttpException::notFound('Reserva no encontrada');
        }

        $availability = new AvailabilityService();

        return $this->view($this->panel . '.bookings.show', [
            'title'     => 'Reserva ' . $booking['public_code'],
            'active'    => 'bookings',
            'booking'   => $booking,
            'history'   => (new BookingStatusHistory())->forBooking((int) $id),
            'payments'  => (new Payment())->forBooking((int) $id),
            'balance'   => (new PaymentService())->balanceFor((int) $id),
            'barbers'   => (new Barber())->availableForService((int) $booking['service_id'], false),
            'services'  => (new Service())->activeAll(),
            'methods'   => PaymentMethod::inStore(),
            'nextStates' => BookingStatus::nextOptions((string) $booking['status']),
            'dates'     => $availability->availableDates((int) $booking['service_id'], (int) $booking['barber_id'], 21, true),
            'basePath'  => $this->basePath,
        ]);
    }

    /** Formulario de creación manual (teléfono, WhatsApp, presencial). */
    public function create(): Response
    {
        $customer = null;

        if ($customerId = $this->request->integer('customer_id')) {
            $customer = (new Customer())->find($customerId);
        }

        return $this->view($this->panel . '.bookings.create', [
            'title'    => 'Nueva reserva',
            'active'   => 'bookings',
            'services' => (new Service())->activeAll(),
            'barbers'  => (new Barber())->activeList(Branch::defaultId()),
            'methods'  => PaymentMethod::inStore(),
            'sources'  => BookingSource::manual(),
            'customer' => $customer,
            'basePath' => $this->basePath,
        ]);
    }

    public function store(): Response
    {
        $validator = new Validator($this->request->all(), [
            'service_id'   => 'required|integer|exists:services,id',
            'barber_id'    => 'required|integer|exists:barbers,id',
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time'   => 'required|time',
            'source'       => 'required|in:' . implode(',', BookingSource::manual()),
            'payment_method' => 'nullable|in:' . implode(',', PaymentMethod::all()),
            'internal_notes' => 'nullable|max:1000',
            'customer_id'  => 'nullable|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return $this->respondError('Revisa los datos de la reserva', $validator->errors());
        }

        $payload = $validator->validated();

        // Cliente nuevo desde el mismo formulario
        if (empty($payload['customer_id'])) {
            $customerValidator = new Validator($this->request->all(), [
                'first_name' => 'required|min:2|max:80',
                'last_name'  => 'required|min:2|max:80',
                'phone'      => 'required|phone',
                'email'      => 'nullable|email|max:150',
                'rut'        => 'nullable|rut',
            ]);

            if ($customerValidator->fails()) {
                return $this->respondError('Completa los datos del cliente', $customerValidator->errors());
            }

            $payload['customer'] = $customerValidator->validated();
        }

        $payload['branch_id'] = Branch::defaultId();

        try {
            $booking = (new BookingService())->create($payload, Auth::id(), true);
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        if ($this->request->expectsJson()) {
            return $this->success('Reserva creada correctamente', [
                'id'       => (int) $booking['id'],
                'code'     => $booking['public_code'],
                'redirect' => url(ltrim($this->basePath, '/') . '/reservas/' . $booking['id']),
            ]);
        }

        return $this->redirectWith(
            $this->basePath . '/reservas/' . $booking['id'],
            'Reserva ' . $booking['public_code'] . ' creada correctamente.'
        );
    }

    public function update(string $id): Response
    {
        $data = $this->request->only(['service_id', 'discount', 'customer_notes', 'internal_notes', 'payment_method']);

        try {
            (new BookingService())->update((int) $id, $data, Auth::id());
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk('Reserva actualizada.', $this->basePath . '/reservas/' . $id);
    }

    public function changeStatus(string $id): Response
    {
        $status = (string) $this->request->input('status');

        try {
            $booking = (new BookingService())->changeStatus(
                (int) $id,
                $status,
                Auth::id(),
                (string) ($this->request->input('note') ?: '') ?: null
            );
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk(
            'Estado actualizado a ' . BookingStatus::label($status) . '.',
            $this->basePath . '/reservas/' . $id,
            ['status' => $booking['status'], 'status_label' => BookingStatus::label((string) $booking['status'])]
        );
    }

    public function reschedule(string $id): Response
    {
        $validator = new Validator($this->request->all(), [
            'booking_date' => 'required|date_format:Y-m-d',
            'start_time'   => 'required|time',
            'barber_id'    => 'nullable|integer|exists:barbers,id',
        ]);

        if ($validator->fails()) {
            return $this->respondError('Fecha u hora inválida', $validator->errors());
        }

        try {
            (new BookingService())->reschedule(
                (int) $id,
                (string) $this->request->input('booking_date'),
                (string) $this->request->input('start_time'),
                $this->request->integer('barber_id'),
                Auth::id(),
                true
            );
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk('Reserva reprogramada.', $this->basePath . '/reservas/' . $id);
    }

    public function changeBarber(string $id): Response
    {
        $barberId = $this->request->integer('barber_id');

        if ($barberId === null) {
            return $this->respondError('Selecciona un barbero.');
        }

        try {
            (new BookingService())->changeBarber((int) $id, $barberId, Auth::id());
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk('Barbero actualizado.', $this->basePath . '/reservas/' . $id);
    }

    public function changeService(string $id): Response
    {
        return $this->update($id);
    }

    public function cancel(string $id): Response
    {
        try {
            (new BookingService())->cancel(
                (int) $id,
                (string) ($this->request->input('reason') ?: 'Cancelada desde el panel'),
                Auth::id(),
                true
            );
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk('Reserva cancelada.', $this->basePath . '/reservas/' . $id);
    }

    public function registerPayment(string $id): Response
    {
        $validator = new Validator($this->request->all(), [
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:' . implode(',', PaymentMethod::all()),
            'notes'          => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respondError('Revisa los datos del pago', $validator->errors());
        }

        try {
            (new PaymentService())->registerManual(
                (int) $id,
                (float) $this->request->input('amount'),
                (string) $this->request->input('payment_method'),
                Auth::id(),
                (string) ($this->request->input('notes') ?: '') ?: null
            );
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage());
        }

        return $this->respondOk('Pago registrado.', $this->basePath . '/reservas/' . $id);
    }

    public function addNote(string $id): Response
    {
        $booking = (new Booking())->findFull((int) $id);

        if ($booking === null) {
            throw HttpException::notFound('Reserva no encontrada');
        }

        $note = trim((string) $this->request->input('note'));

        if ($note === '') {
            return $this->respondError('Escribe la nota antes de guardar.');
        }

        (new CustomerService())->addNote(
            (int) $booking['customer_id'],
            $note,
            (string) ($this->request->input('type') ?: 'service'),
            Auth::id(),
            (int) $id,
            $this->request->boolean('pinned')
        );

        return $this->respondOk('Nota guardada.', $this->basePath . '/reservas/' . $id);
    }

    // -----------------------------------------------------------------
    //  Respuestas (soporta formulario y fetch con el mismo código)
    // -----------------------------------------------------------------

    protected function respondOk(string $message, string $redirect, array $data = []): Response
    {
        if ($this->request->expectsJson()) {
            return $this->success($message, $data);
        }

        Session::flash('success', $message);

        return $this->redirect($redirect);
    }

    protected function respondError(string $message, array $errors = [], int $status = 422): Response
    {
        if ($this->request->expectsJson()) {
            return $this->fail($message, $errors, $status);
        }

        if ($errors !== []) {
            return $this->backWithErrors($errors, $message);
        }

        Session::flash('error', $message);

        return $this->back();
    }
}
