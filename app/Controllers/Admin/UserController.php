<?php
/**
 * Ruta: /app/Controllers/Admin/UserController.php
 * Usuarios internos. Un usuario nunca puede crear o editar a alguien de rol
 * superior al suyo (spec §54).
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Branch;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Role;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class UserController extends Controller
{
    public function index(): Response
    {
        $filters = $this->request->only(['role', 'status', 'search']);

        return $this->view('admin.users.index', [
            'title'   => 'Usuarios internos',
            'active'  => 'users',
            'users'   => (new User())->listWithBarber($filters),
            'filters' => $filters,
            'roles'   => Role::all(),
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin.users.form', [
            'title'    => 'Nuevo usuario',
            'active'   => 'users',
            'user'     => null,
            'roles'    => Role::assignableBy(Auth::role()),
            'branches' => (new Branch())->active(),
            'barbers'  => (new Barber())->activeList(),
        ]);
    }

    public function edit(string $id): Response
    {
        $user = (new User())->findOrFail((int) $id);
        $this->guardHierarchy($user);

        return $this->view('admin.users.form', [
            'title'    => 'Editar usuario',
            'active'   => 'users',
            'user'     => (new User())->withoutHidden($user),
            'roles'    => Role::assignableBy(Auth::role()),
            'branches' => (new Branch())->active(),
            'barbers'  => (new Barber())->activeList(),
        ]);
    }

    public function store(): Response
    {
        $validator = new Validator($this->request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'email'      => 'required|email|max:150|unique:users,email',
            'phone'      => 'nullable|phone',
            'role'       => 'required|in:' . implode(',', Role::assignableBy(Auth::role())),
            'password'   => 'required|min:8|max:100|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $validator->validated();

        $data['branch_id']            = $this->request->integer('branch_id') ?: Branch::defaultId();
        $data['status']               = 1;
        $data['must_change_password'] = $this->request->boolean('must_change_password', true) ? 1 : 0;

        $id = (new User())->createUser($data);

        // Vincular con una ficha de barbero existente.
        if ($data['role'] === Role::BARBER && ($barberId = $this->request->integer('barber_id'))) {
            (new Barber())->update($barberId, ['user_id' => $id]);
        }

        ActivityLogger::log('user.created', 'user', $id, sprintf('Usuario %s (%s)', $data['email'], Role::label($data['role'])));

        return $this->redirectWith('/admin/usuarios', 'Usuario creado correctamente.');
    }

    public function update(string $id): Response
    {
        $model  = new User();
        $before = $model->findOrFail((int) $id);
        $this->guardHierarchy($before);

        $rules = [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'email'      => 'required|email|max:150|unique:users,email,' . (int) $id,
            'phone'      => 'nullable|phone',
            'role'       => 'required|in:' . implode(',', Role::assignableBy(Auth::role())),
        ];

        if ($this->request->filled('password')) {
            $rules['password'] = 'min:8|max:100|confirmed';
        }

        $validator = new Validator($this->request->all(), $rules);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $validator->validated();
        unset($data['password']);

        $data['branch_id'] = $this->request->integer('branch_id') ?: $before['branch_id'];

        // No dejar el sistema sin SUPER_ADMIN activo.
        if (
            $before['role'] === Role::SUPER_ADMIN
            && $data['role'] !== Role::SUPER_ADMIN
            && $model->activeSuperAdminCount((int) $id) === 0
        ) {
            Session::flash('error', 'Debe existir al menos un Súper Administrador activo.');

            return $this->back();
        }

        $model->update((int) $id, $data);

        if ($this->request->filled('password')) {
            $model->updatePassword((int) $id, (string) $this->request->input('password'));
            Session::flash('info', 'Contraseña actualizada.');
        }

        ActivityLogger::logChanges('user.updated', 'user', (int) $id, $before, $model->find((int) $id) ?? [], 'Usuario ' . $before['email']);

        return $this->redirectWith('/admin/usuarios', 'Usuario actualizado.');
    }

    public function toggleStatus(string $id): Response
    {
        $model = new User();
        $user  = $model->findOrFail((int) $id);
        $this->guardHierarchy($user);

        if ((int) $id === Auth::id()) {
            Session::flash('error', 'No puedes desactivar tu propia cuenta.');

            return $this->back('/admin/usuarios');
        }

        $status = (int) $user['status'] === 1 ? 0 : 1;

        if (
            $status === 0
            && $user['role'] === Role::SUPER_ADMIN
            && $model->activeSuperAdminCount((int) $id) === 0
        ) {
            Session::flash('error', 'Debe existir al menos un Súper Administrador activo.');

            return $this->back('/admin/usuarios');
        }

        $model->update((int) $id, ['status' => $status]);
        ActivityLogger::log('user.updated', 'user', (int) $id, ($status ? 'Activó' : 'Desactivó') . ' a ' . $user['email']);

        Session::flash('success', $status ? 'Usuario activado.' : 'Usuario desactivado.');

        return $this->back('/admin/usuarios');
    }

    /** Nadie puede editar a un usuario de rango superior al propio. */
    private function guardHierarchy(array $target): void
    {
        $this->authorize(
            Role::level(Auth::role()) >= Role::level((string) $target['role']),
            'No puedes administrar usuarios de un rol superior al tuyo.'
        );
    }
}
