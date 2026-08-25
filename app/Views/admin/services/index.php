<?php
/**
 * Ruta: /app/Views/admin/services/index.php
 */

use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Servicios</h1>
        <p class="sub"><?= count($services) ?> servicio(s) · precios y duración</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/servicios/nuevo')) ?>" class="btn btn-primary btn-sm">+ Nuevo servicio</a>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre del servicio">
        </div>
        <div class="field">
            <label class="label" for="category_id">Categoría</label>
            <select class="select" id="category_id" name="category_id" data-auto-submit>
                <option value="">Todas</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="label" for="status">Estado</label>
            <select class="select" id="status" name="status" data-auto-submit>
                <option value="">Todos</option>
                <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Inactivos</option>
            </select>
        </div>
        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Filtrar</button>
        </div>
    </div>
</form>

<div class="card card-flush">
    <?php if ($services === []): ?>
        <?php
            $icon = 'bottle';
            $message = 'Aún no hay servicios';
            $action = '<a href="' . e(url('admin/servicios/nuevo')) . '" class="btn btn-primary btn-sm">Crear el primero</a>';
            require View::path('components.empty');
        ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Categoría</th>
                        <th class="right">Duración</th>
                        <th class="right">Precio</th>
                        <th class="right">Barberos</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>
                                <div class="row row-nowrap gap-sm">
                                    <span style="width:4px;height:30px;border-radius:3px;background:<?= e($service['color'] ?: '#FFC400') ?>"></span>
                                    <div>
                                        <a href="<?= e(url('admin/servicios/' . $service['id'] . '/editar')) ?>" class="bold"><?= e($service['name']) ?></a>
                                        <?php if ((int) $service['is_featured'] === 1): ?>
                                            <span class="badge badge-confirmed">Destacado</span>
                                        <?php endif; ?>
                                        <?php if (!empty($service['description'])): ?>
                                            <div class="tiny muted"><?= e(Str::limit($service['description'], 62)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="small"><?= e($service['category_name'] ?? '—') ?></td>
                            <td class="right"><?= (int) $service['duration_minutes'] ?> min</td>
                            <td class="right bold"><?= e(money($service['price'])) ?></td>
                            <td class="right">
                                <?php if ((int) $service['barbers_count'] === 0): ?>
                                    <span class="badge badge-noshow" title="Nadie lo realiza: no aparece en el booking">0</span>
                                <?php else: ?>
                                    <?= (int) $service['barbers_count'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $service['status'] === 1): ?>
                                    <span class="badge badge-checkedin">Activo</span>
                                    <?php if ((int) $service['online_bookable'] === 0): ?>
                                        <span class="badge badge-muted">Sólo interno</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge badge-cancelled">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(url('admin/servicios/' . $service['id'] . '/editar')) ?>" class="btn btn-xs btn-light">Editar</a>
                                    <form method="post" action="<?= e(url('admin/servicios/' . $service['id'] . '/estado')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-ghost">
                                            <?= (int) $service['status'] === 1 ? 'Ocultar' : 'Activar' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
