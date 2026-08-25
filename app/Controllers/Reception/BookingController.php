<?php
/**
 * Ruta: /app/Controllers/Reception/BookingController.php
 *
 * Recepción reutiliza el controlador de administración (mismo motor y misma
 * lógica, spec §71) cambiando sólo el panel de vistas y las rutas base.
 * Las acciones sensibles quedan fuera por las rutas y por los permisos.
 */

namespace App\Controllers\Reception;

use App\Models\Barber;
use App\Models\Branch;
use App\Models\Service;
use App\Services\BookingService;
use App\Support\BookingSource;
use App\Support\BookingStatus;
use App\Support\PaymentMethod;
use Core\Auth;
use Core\Exceptions\BookingException;
use Core\Response;
use Core\Validator;

class BookingController extends \App\Controllers\Admin\BookingController
{
    protected string $panel = 'reception';
    protected string $basePath = '/recepcion';

    /** Walk-in: cliente que llega sin reserva (spec §91). */
    public function walkIn(): Response
    {
        $date = today();

        return $this->view('reception.bookings.walkin', [
            'title'    => 'Walk-in',
            'active'   => 'walkin',
            'services' => (new Service())->activeAll(),
            'barbers'  => (new Barber())->activeList(Branch::defaultId()),
            'methods'  => PaymentMethod::inStore(),
            'date'     => $date,
            'now'      => now()->format('H:i'),
            'basePath' => $this->basePath,
        ]);
    }

    public function storeWalkIn(): Response
    {
        $validator = new Validator($this->request->all(), [
            'service_id' => 'required|integer|exists:services,id',
            'barber_id'  => 'required|integer|exists:barbers,id',
            'start_time' => 'required|time',
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'nullable|max:80',
            'phone'      => 'nullable|phone',
            'rut'        => 'nullable|rut',
        ]);

        if ($validator->fails()) {
            return $this->respondError('Completa los datos del walk-in', $validator->errors());
        }

        $data = $validator->validated();

        try {
            $booking = (new BookingService())->create([
                'service_id'   => (int) $data['service_id'],
                'barber_id'    => (int) $data['barber_id'],
                'booking_date' => today(),
                'start_time'   => $data['start_time'],
                'branch_id'    => Branch::defaultId(),
                'source'       => BookingSource::WALK_IN,
                'payment_method' => $this->request->input('payment_method'),
                'customer'     => [
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'] ?: '',
                    'phone'      => $data['phone'] ?? null,
                    'rut'        => $data['rut'] ?? null,
                ],
            ], Auth::id(), true);
        } catch (BookingException $e) {
            return $this->respondError($e->getMessage(), [], 409);
        }

        return $this->respondOk(
            'Walk-in registrado: ' . $booking['public_code'] . ' · ' . BookingStatus::label((string) $booking['status']),
            $this->basePath . '/reservas/' . $booking['id']
        );
    }
}
