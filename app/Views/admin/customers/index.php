<?php
/**
 * Ruta: /app/Views/admin/customers/index.php
 * CRM: listado con búsqueda por nombre, RUT, teléfono o email (spec §90).
 */

use App\Support\Rut;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');

$rows  = $result['data'];
$query = array_filter($filters, static fn ($v): bool => $v !== null && $v !== '');
?>

<div class="page-head">
    <div>
        <h1>Clientes</h1>
        <p class="sub"><?= number_format((int) $result['total'], 0, ',', '.') ?> cliente(s) en la base</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/nuevo')) ?>" class="btn btn-primary btn-sm">+ Nuevo cliente</a>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Nombre, RUT, teléfono o email" autofocus>
        </div>

        <div class="field">
            <label class="label" for="barber_id">Barbero habitual</label>
            <select class="select" id="barber_id" name="barber_id" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($barbers as $barber): ?>
                    <option value="<?= (int) $barber['id'] ?>" <?= (int) ($filters['barber_id'] ?? 0) === (int) $barber['id'] ? 'selected' : '' ?>>
                        <?= e($barber['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label class="label" for="sort">Ordenar por</label>
            <select class="select" id="sort" name="sort" data-auto-submit>
                <option value="">Última visita</option>
                <option value="spent"  <?= ($filters['sort'] ?? '') === 'spent'  ? 'selected' : '' ?>>Total gastado</option>
                <option value="visits" <?= ($filters['sort'] ?? '') === 'visits' ? 'selected' : '' ?>>Cantidad de visitas</option>
                <option value="name"   <?= ($filters['sort'] ?? '') === 'name'   ? 'selected' : '' ?>>Nombre</option>
            </select>
        </div>

        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Buscar</button>
            <a href="<?= e(url(ltrim($basePath, '/') . '/clientes')) ?>" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </div>
</form>

<div class="card card-flush">
    <?php if ($rows === []): ?>
        <?php
            $icon = 'users';
            $message = 'No encontramos clientes';
            $hint = 'Cada reserva crea la ficha automáticamente.';
            require View::path('components.empty');
        ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th class="right">Visitas</th>
                        <th class="right">No-show</th>
                        <th class="right">Gastado</th>
                        <th>Última visita</th>
                        <th>Barbero</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $customer): ?>
                        <tr>
                            <td>
                                <div class="row row-nowrap gap-sm">
                                    <span class="avatar avatar-sm"><?= e(Str::initials($customer['first_name'], $customer['last_name'])) ?></span>
                                    <div>
                                        <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $customer['id'])) ?>" class="bold">
                                            <?= e(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?>
                                        </a>
                                        <?php if (!empty($customer['rut'])): ?>
                                            <div class="tiny muted mono"><?= e(Rut::format($customer['rut'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small">
                                <?php if (!empty($customer['phone'])): ?>
                                    <a href="tel:<?= e($customer['phone']) ?>"><?= e(Str::phoneDisplay($customer['phone'])) ?></a>
                                <?php endif; ?>
                                <?php if (!empty($customer['email'])): ?>
                                    <div class="tiny muted truncate" style="max-width:180px"><?= e($customer['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="right bold"><?= (int) $customer['completed_bookings'] ?></td>
                            <td class="right">
                                <?php if ((int) $customer['no_show_count'] > 0): ?>
                                    <span class="badge badge-noshow"><?= (int) $customer['no_show_count'] ?></span>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="right bold nowrap"><?= e(money($customer['total_spent'])) ?></td>
                            <td class="small nowrap">
                                <?= $customer['last_visit_at'] ? e(\App\Support\DateHelper::shortEs($customer['last_visit_at'])) : '<span class="muted">—</span>' ?>
                            </td>
                            <td class="small"><?= e($customer['preferred_barber_name'] ?? '—') ?></td>
                            <td class="right">
                                <a href="<?= e(url(ltrim($basePath, '/') . '/clientes/' . $customer['id'])) ?>" class="btn btn-xs btn-light">Ficha</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php require View::path('components.pagination'); ?>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
