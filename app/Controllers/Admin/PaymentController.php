<?php
/**
 * Ruta: /app/Controllers/Admin/PaymentController.php
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\PaymentMethod;
use App\Support\PaymentStatus;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;

class PaymentController extends Controller
{
    public function index(): Response
    {
        $filters = $this->request->only(['status', 'payment_method', 'date_from', 'date_to', 'barber_id', 'search']);

        if (!isset($filters['date_from'])) {
            $filters['date_from'] = \App\Support\DateHelper::startOfMonth(today());
        }

        return $this->view('admin.payments.index', [
            'title'    => 'Pagos',
            'active'   => 'payments',
            'result'   => (new Payment())->paginateFiltered($filters, max(1, $this->request->integer('page', 1)), 30),
            'filters'  => $filters,
            'barbers'  => (new Barber())->activeList(),
            'methods'  => PaymentMethod::all(),
            'statuses' => PaymentStatus::all(),
        ]);
    }

    public function refund(string $id): Response
    {
        $amount = $this->request->filled('amount') ? (float) $this->request->input('amount') : null;

        try {
            (new PaymentService())->refund(
                (int) $id,
                $amount,
                Auth::id(),
                (string) ($this->request->input('reason') ?: '') ?: null
            );
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());

            return $this->back('/admin/pagos');
        }

        Session::flash('success', 'Reembolso registrado.');

        return $this->back('/admin/pagos');
    }
}
