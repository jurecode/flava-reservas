<?php
/**
 * Ruta: /app/Views/admin/activity.php
 * Auditoría de operaciones importantes (spec §60).
 */

use App\Services\ActivityLogger;
use App\Support\Role;
use Core\View;

View::layout('panel');
View::start('content');

$rows  = $result['data'];
$query = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
?>

<div class="page-head">
    <div>
        <h1>Auditoría</h1>
        <p class="sub">Quién hizo qué y cuándo. <?= number_format((int) $result['total'], 0, ',', '.') ?> registro(s).</p>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="user_id">Usuario</label>
            <select class="select" id="user_id" name="user_id" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        <?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="entity_type">Entidad</label>
            <select class="select" id="entity_type" name="entity_type" data-auto-submit>
                <option value="">Todas</option>
                <?php foreach (['booking' => 'Reservas', 'customer' => 'Clientes', 'barber' => 'Barberos', 'service' => 'Servicios', 'user' => 'Usuarios', 'settings' => 'Configuración', 'deployment' => 'Despliegues', 'system' => 'Sistema'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['entity_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="date_from">Desde</label>
            <input class="input" type="date" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
        </div>

        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Filtrar</button>
            <a href="<?= e(url('admin/auditoria')) ?>" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </div>
</form>

<div class="card card-flush">
    <?php if ($rows === []): ?>
        <?php $icon = 'receipt'; $message = 'Sin registros de actividad'; require View::path('components.empty'); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cuándo</th>
                        <th>Quién</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $log): ?>
                        <tr>
                            <td class="small nowrap muted"><?= e(substr((string) $log['created_at'], 0, 16)) ?></td>
                            <td class="small">
                                <?php if ($log['user_id'] !== null): ?>
                                    <span class="bold"><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></span>
                                    <div class="tiny"><span class="badge <?= e(Role::badgeClass($log['role'])) ?>"><?= e(Role::label($log['role'])) ?></span></div>
                                <?php else: ?>
                                    <span class="muted">Sistema / cliente</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e(ucfirst(ActivityLogger::describe((string) $log['action']))) ?></td>
                            <td class="small muted"><?= e($log['description'] ?? '—') ?></td>
                            <td class="tiny muted mono"><?= e($log['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require View::path('components.pagination'); ?>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
