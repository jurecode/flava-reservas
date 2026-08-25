<?php
/**
 * Ruta: /app/Controllers/Admin/BarberController.php
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UploadService;
use App\Support\Role;
use App\Support\Str;
use Core\Controller;
use Core\Database;
use Core\Response;
use Core\Session;
use Core\Validator;

class BarberController extends Controller
{
    public function index(): Response
    {
        $filters = $this->request->only(['search', 'status']);

        return $this->view('admin.barbers.index', [
            'title'   => 'Barberos',
            'active'  => 'barbers',
            'barbers' => (new Barber())->listWithStats($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin.barbers.form', [
            'title'    => 'Nuevo barbero',
            'active'   => 'barbers',
            'barber'   => null,
            'services' => (new Service())->activeAll(),
            'assigned' => [],
            'branches' => (new Branch())->active(),
        ]);
    }

    public function edit(string $id): Response
    {
        $model  = new Barber();
        $barber = $model->findOrFail((int) $id);

        return $this->view('admin.barbers.form', [
            'title'    => 'Editar barbero',
            'active'   => 'barbers',
            'barber'   => $barber,
            'services' => (new Service())->activeAll(),
            'assigned' => $model->serviceIds((int) $id),
            'branches' => (new Branch())->active(),
            'user'     => $barber['user_id'] ? (new User())->find((int) $barber['user_id']) : null,
        ]);
    }

    public function store(): Response
    {
        $validator = new Validator($this->request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $this->prepare($validator->validated());

        $barberId = Database::instance()->transaction(function () use ($data): int {
            $model = new Barber();
            $data['slug'] = Str::uniqueSlug($data['display_name'], 'barbers');

            // Cuenta de acceso opcional al panel del barbero.
            if ($this->request->boolean('create_user') && !empty($data['email'])) {
                $password = (string) ($this->request->input('password') ?: bin2hex(random_bytes(5)));

                $data['user_id'] = (new User())->createUser([
                    'branch_id'            => $data['branch_id'],
                    'first_name'           => $data['first_name'],
                    'last_name'            => $data['last_name'] ?: '',
                    'email'                => $data['email'],
                    'phone'                => $data['phone'] ?? null,
                    'password'             => $password,
                    'role'                 => Role::BARBER,
                    'status'               => 1,
                    'must_change_password' => 1,
                ]);

                Session::flash('info', 'Cuenta creada para ' . $data['email'] . ' · Contraseña temporal: ' . $password);
            }

            return $model->create($data);
        });

        $model = new Barber();
        $model->syncServices($barberId, (array) $this->request->raw('services', []));

        try {
            $photo = $this->handlePhoto(null, slugify($data['display_name']));
            if ($photo !== null) {
                $model->update($barberId, ['photo' => $photo]);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', 'El barbero se creó, pero la foto no: ' . $e->getMessage());
        }

        ActivityLogger::log('barber.created', 'barber', $barberId, 'Barbero ' . $data['display_name']);

        return $this->redirectWith('/admin/barberos/' . $barberId . '/horario', 'Barbero creado. Ahora define su horario semanal.');
    }

    public function update(string $id): Response
    {
        $model  = new Barber();
        $before = $model->findOrFail((int) $id);

        $validator = new Validator($this->request->all(), $this->rules((int) $id));

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $this->prepare($validator->validated());

        if ($data['display_name'] !== $before['display_name']) {
            $data['slug'] = Str::uniqueSlug($data['display_name'], 'barbers', 'slug', (int) $id);
        }

        try {
            $data['photo'] = $this->handlePhoto($before['photo'], slugify($data['display_name']));
        } catch (\RuntimeException $e) {
            return $this->backWithErrors(['photo' => [$e->getMessage()]]);
        }

        $model->update((int) $id, $data);
        $model->syncServices((int) $id, (array) $this->request->raw('services', []));

        // Mantiene sincronizados los datos de la cuenta asociada.
        if ($before['user_id'] !== null) {
            (new User())->update((int) $before['user_id'], [
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?: '',
                'phone'      => $data['phone'] ?? null,
            ]);
        }

        ActivityLogger::logChanges('barber.updated', 'barber', (int) $id, $before, $model->find((int) $id) ?? [], 'Barbero ' . $before['display_name']);

        return $this->redirectWith('/admin/barberos', 'Barbero actualizado.');
    }

    public function toggleStatus(string $id): Response
    {
        $model  = new Barber();
        $barber = $model->findOrFail((int) $id);
        $status = (int) $barber['status'] === 1 ? 0 : 1;

        $model->update((int) $id, ['status' => $status]);

        // Desactivar el barbero también bloquea su acceso al panel.
        if ($barber['user_id'] !== null) {
            (new User())->update((int) $barber['user_id'], ['status' => $status]);
        }

        ActivityLogger::log('barber.updated', 'barber', (int) $id, ($status ? 'Activó' : 'Desactivó') . ' a ' . $barber['display_name']);

        Session::flash('success', $status ? 'Barbero activado.' : 'Barbero desactivado. Sus reservas existentes se mantienen.');

        return $this->back('/admin/barberos');
    }

    /** Sube, conserva o quita la foto del barbero según lo que envíe el formulario. */
    private function handlePhoto(?string $current, string $slug): ?string
    {
        $uploads = new UploadService();

        if ($this->request->boolean('photo_remove')) {
            $uploads->delete($current);

            return null;
        }

        return $uploads->replace($this->request->file('photo'), $current, 'barbers', $slug);
    }

    private function rules(?int $ignoreId = null): array
    {
        $ignore = $ignoreId !== null ? ',' . $ignoreId : '';

        return [
            'first_name'   => 'required|min:2|max:80',
            'last_name'    => 'nullable|max:80',
            'display_name' => 'required|min:2|max:80',
            'email'        => 'nullable|email|max:150',
            'phone'        => 'nullable|phone',
            'specialty'    => 'nullable|max:160',
            'bio'          => 'nullable|max:1000',
            'instagram'    => 'nullable|max:120',
            'color'        => 'nullable|max:9',
            'sort_order'   => 'nullable|integer|min:0',
            'branch_id'    => 'nullable|integer|exists:branches,id',
        ];
    }

    private function prepare(array $data): array
    {
        $data['branch_id']      = (int) ($data['branch_id'] ?? Branch::defaultId());
        $data['sort_order']     = (int) ($data['sort_order'] ?? 0);
        $data['color']          = $data['color'] ?: '#FFC400';
        $data['status']         = $this->request->boolean('status') ? 1 : 0;
        $data['accepts_online'] = $this->request->boolean('accepts_online') ? 1 : 0;
        $data['email']          = $data['email'] ?: null;

        return $data;
    }
}
