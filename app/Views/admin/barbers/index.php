<?php
/**
 * Ruta: /app/Views/admin/barbers/index.php
 */

use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Barberos</h1>
        <p class="sub"><?= count($barbers) ?> ficha(s) · define servicios y horarios de cada uno</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('admin/barberos/nuevo')) ?>" class="btn btn-primary btn-sm">+ Nuevo barbero</a>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="search">Buscar</label>
            <input class="input" type="search" id="search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nombre">
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

<?php if ($barbers === []): ?>
    <div class="card">
        <?php
            $icon = 'scissors';
            $message = 'Aún no hay barberos';
            $hint = 'Crea el primero para poder recibir reservas.';
            $action = '<a href="' . e(url('admin/barberos/nuevo')) . '" class="btn btn-primary btn-sm">Crear barbero</a>';
            require View::path('components.empty');
        ?>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($barbers as $barber): ?>
            <div class="card card-hover">
                <div class="row row-nowrap gap-sm mb-2">
                    <?php if (!empty($barber['photo'])): ?>
                        <img src="<?= e(upload_url($barber['photo'])) ?>" alt="" class="avatar avatar-lg">
                    <?php else: ?>
                        <span class="avatar avatar-lg" style="background:<?= e($barber['color']) ?>22;color:<?= e($barber['color']) ?>">
                            <?= e(Str::initials($barber['first_name'], $barber['last_name'])) ?>
                        </span>
                    <?php endif; ?>

                    <div class="grow">
                        <strong><?= e($barber['display_name']) ?></strong>
                        <div class="small muted"><?= e($barber['specialty'] ?: 'Sin especialidad definida') ?></div>
                        <div class="mt-1">
                            <?php if ((int) $barber['status'] === 1): ?>
                                <span class="badge badge-checkedin">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-cancelled">Inactivo</span>
                            <?php endif; ?>
                            <?php if ((int) $barber['accepts_online'] === 0): ?>
                                <span class="badge badge-muted">Sólo interno</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row-between small muted" style="padding:9px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line)">
                    <span><?= (int) $barber['services_count'] ?> servicio(s)</span>
                    <span><?= (int) $barber['bookings_today'] ?> reserva(s) hoy</span>
                </div>

                <?php if (!empty($barber['user_email'])): ?>
                    <div class="tiny muted mt-1"><?= icon('lock', 13) ?> <?= e($barber['user_email']) ?></div>
                <?php else: ?>
                    <div class="tiny muted mt-1">Sin cuenta de acceso al panel</div>
                <?php endif; ?>

                <div class="row gap-sm mt-2">
                    <a href="<?= e(url('admin/barberos/' . $barber['id'] . '/editar')) ?>" class="btn btn-light btn-sm grow">Editar</a>
                    <a href="<?= e(url('admin/barberos/' . $barber['id'] . '/horario')) ?>" class="btn btn-ghost btn-sm grow">Horario</a>
                </div>

                <form method="post" action="<?= e(url('admin/barberos/' . $barber['id'] . '/estado')) ?>" class="mt-1"
                      data-confirm="<?= (int) $barber['status'] === 1 ? '¿Desactivar a ' . e($barber['display_name']) . '? No recibirá nuevas reservas.' : '¿Activar a ' . e($barber['display_name']) . '?' ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-xs btn-ghost btn-block">
                        <?= (int) $barber['status'] === 1 ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php View::stop(); ?>
