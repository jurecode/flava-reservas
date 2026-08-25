<?php
/**
 * Ruta: /app/Views/admin/users/index.php
 */

use App\Support\Role;
use App\Support\Str;
use Core\Auth;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Usuarios internos</h1>
        <p class="sub">Accesos al panel. Los clientes no necesitan cuenta.</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/usuarios/nuevo')) ?>" class="btn btn-primary btn-sm">+ Nuevo usuario</a>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre o email">
        </div>
        <div class="field">
            <label class="label" for="role">Rol</label>
            <select class="select" id="role" name="role" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($roles as $roleValue): ?>
                    <option value="<?= e($roleValue) ?>" <?= ($filters['role'] ?? '') === $roleValue ? 'selected' : '' ?>>
                        <?= e(Role::label($roleValue)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Filtrar</button>
        </div>
    </div>
</form>

<div class="card card-flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Barbero vinculado</th>
                    <th>Último ingreso</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="row row-nowrap gap-sm">
                                <span class="avatar avatar-sm"><?= e(Str::initials($user['first_name'], $user['last_name'])) ?></span>
                                <div>
                                    <span class="bold"><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?></span>
                                    <?php if ((int) $user['id'] === Auth::id()): ?>
                                        <span class="badge badge-muted">Tú</span>
                                    <?php endif; ?>
                                    <div class="tiny muted"><?= e($user['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?= e(Role::badgeClass($user['role'])) ?>"><?= e(Role::label($user['role'])) ?></span></td>
                        <td class="small"><?= e($user['barber_name'] ?? '—') ?></td>
                        <td class="small muted"><?= e($user['last_login_at'] ? substr((string) $user['last_login_at'], 0, 16) : 'Nunca') ?></td>
                        <td>
                            <?php if ((int) $user['status'] === 1): ?>
                                <span class="badge badge-checkedin">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled">Desactivado</span>
                            <?php endif; ?>
                            <?php if ((int) $user['must_change_password'] === 1): ?>
                                <span class="badge badge-pending">Debe cambiar clave</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php if (Role::level(Auth::role()) >= Role::level($user['role'])): ?>
                                    <a href="<?= e(url('admin/usuarios/' . $user['id'] . '/editar')) ?>" class="btn btn-xs btn-light">Editar</a>
                                    <?php if ((int) $user['id'] !== Auth::id()): ?>
                                        <form method="post" action="<?= e(url('admin/usuarios/' . $user['id'] . '/estado')) ?>"
                                              data-confirm="<?= (int) $user['status'] === 1 ? '¿Desactivar el acceso de ' . e($user['email']) . '?' : '¿Reactivar el acceso?' ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-xs btn-ghost">
                                                <?= (int) $user['status'] === 1 ? 'Desactivar' : 'Activar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="tiny muted">Sin permisos</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card card-muted mt-3">
    <h3 style="font-size:.95rem">Qué puede hacer cada rol</h3>
    <div class="grid-4 mt-2">
        <div><span class="badge badge-super">Súper Admin</span><p class="tiny muted mt-1 mb-0">Todo, más GitHub, despliegues, migraciones, respaldos y logs.</p></div>
        <div><span class="badge badge-admin">Administrador</span><p class="tiny muted mt-1 mb-0">Reservas, clientes, barberos, servicios, precios, pagos y reportes.</p></div>
        <div><span class="badge badge-reception">Recepción</span><p class="tiny muted mt-1 mb-0">Agenda, reservas, clientes, pagos manuales y bloqueos.</p></div>
        <div><span class="badge badge-barber">Barbero</span><p class="tiny muted mt-1 mb-0">Sólo su agenda, sus clientes y sus bloqueos.</p></div>
    </div>
</div>

<?php View::stop(); ?>
