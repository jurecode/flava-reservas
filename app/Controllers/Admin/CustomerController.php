<?php
/**
 * Ruta: /app/Controllers/Admin/CustomerController.php
 * CRM: fichas, historial y notas (spec §31, §32).
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Services\CustomerService;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class CustomerController extends Controller
{
    protected string $panel = 'admin';
    protected string $basePath = '/admin';

    public function index(): Response
    {
        $filters = $this->request->only(['search', 'barber_id', 'sort', 'only_no_show']);

        $result = (new Customer())->paginateFiltered(
            $filters,
            max(1, $this->request->integer('page', 1)),
            25
        );

        return $this->view($this->panel . '.customers.index', [
            'title'    => 'Clientes',
            'active'   => 'customers',
            'result'   => $result,
            'filters'  => $filters,
            'barbers'  => (new Barber())->activeList(),
            'basePath' => $this->basePath,
        ]);
    }

    public function show(string $id): Response
    {
        $profile = (new CustomerService())->profile((int) $id);

        return $this->view($this->panel . '.customers.show', [
            'title'    => (new Customer())->fullName($profile['customer']),
            'active'   => 'customers',
            'customer' => $profile['customer'],
            'history'  => $profile['history'],
            'next'     => $profile['next_booking'],
            'serviceNotes' => $profile['service_notes'],
            'adminNotes'   => $profile['admin_notes'],
            'barbers'  => (new Barber())->activeList(),
            'basePath' => $this->basePath,
        ]);
    }

    public function create(): Response
    {
        return $this->view($this->panel . '.customers.form', [
            'title'    => 'Nuevo cliente',
            'active'   => 'customers',
            'customer' => null,
            'barbers'  => (new Barber())->activeList(),
            'basePath' => $this->basePath,
        ]);
    }

    public function edit(string $id): Response
    {
        return $this->view($this->panel . '.customers.form', [
            'title'    => 'Editar cliente',
            'active'   => 'customers',
            'customer' => (new Customer())->findOrFail((int) $id),
            'barbers'  => (new Barber())->activeList(),
            'basePath' => $this->basePath,
        ]);
    }

    public function store(): Response
    {
        $validator = new Validator($this->request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->respondError('Revisa los datos del cliente', $validator->errors());
        }

        try {
            $customer = (new CustomerService())->create($validator->validated(), Branch::defaultId());
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage());
        }

        if ($this->request->expectsJson()) {
            return $this->success('Cliente creado', [
                'id'   => (int) $customer['id'],
                'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
                'rut'  => $customer['rut'],
                'phone' => $customer['phone'],
            ]);
        }

        return $this->redirectWith($this->basePath . '/clientes/' . $customer['id'], 'Cliente creado correctamente.');
    }

    public function update(string $id): Response
    {
        $validator = new Validator($this->request->all(), $this->rules((int) $id));

        if ($validator->fails()) {
            return $this->respondError('Revisa los datos del cliente', $validator->errors());
        }

        try {
            (new CustomerService())->update((int) $id, $validator->validated());
        } catch (\RuntimeException $e) {
            return $this->respondError($e->getMessage());
        }

        return $this->respondOk('Cliente actualizado.', $this->basePath . '/clientes/' . $id);
    }

    public function addNote(string $id): Response
    {
        $note = trim((string) $this->request->input('note'));

        if ($note === '') {
            return $this->respondError('Escribe la nota antes de guardar.');
        }

        (new CustomerService())->addNote(
            (int) $id,
            $note,
            (string) ($this->request->input('type') ?: CustomerNote::TYPE_SERVICE),
            Auth::id(),
            $this->request->integer('booking_id'),
            $this->request->boolean('pinned')
        );

        return $this->respondOk('Nota agregada.', $this->basePath . '/clientes/' . $id);
    }

    private function rules(?int $ignoreId = null): array
    {
        $rut = trim((string) $this->request->input('rut'));

        return [
            'first_name'     => 'required|min:2|max:80',
            'last_name'      => 'required|min:2|max:80',
            'rut'            => $rut === '' ? 'nullable' : 'rut',
            'email'          => 'nullable|email|max:150',
            'phone'          => 'required|phone',
            'whatsapp_phone' => 'nullable|phone',
            'birthday'       => 'nullable|date_format:Y-m-d',
            'notes'          => 'nullable|max:2000',
            'preferred_barber_id' => 'nullable|integer|exists:barbers,id',
        ];
    }

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
