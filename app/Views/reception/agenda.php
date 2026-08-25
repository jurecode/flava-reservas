<?php
/**
 * Ruta: /app/Views/reception/agenda.php
 * Agenda del día en columnas por barbero.
 */

use App\Models\BlockedTime;
use Core\View;

View::layout('panel');
View::start('content');

$basePath = '/recepcion';
?>

<div class="page-head">
    <div>
        <h1>Agenda</h1>
        <p class="sub"><?= e(ucfirst(date_es($date, true))) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('recepcion/agenda?date=' . $prev)) ?>" class="btn btn-light btn-sm">‹</a>
        <a href="<?= e(url('recepcion/agenda?date=' . today())) ?>" class="btn btn-light btn-sm">Hoy</a>
        <a href="<?= e(url('recepcion/agenda?date=' . $next)) ?>" class="btn btn-light btn-sm">›</a>
        <form method="get" class="row gap-sm" style="margin-left:8px">
            <input class="input" type="date" name="date" value="<?= e($date) ?>" style="padding:8px 11px;font-size:.86rem" data-auto-submit>
        </form>
    </div>
</div>

<?php if ($columns === []): ?>
    <div class="card">
        <?php
            $icon = 'palm';
            $message = 'Nadie trabaja este día';
            $hint = 'Revisa los horarios semanales de los barberos.';
            require View::path('components.empty');
        ?>
    </div>
<?php else: ?>
    <div class="agenda-cols">
        <?php foreach ($columns as $column): ?>
            <?php $barber = $column['barber']; ?>
            <div class="agenda-col">
                <div class="agenda-col-head">
                    <span style="width:9px;height:9px;border-radius:50%;background:<?= e($barber['color']) ?>"></span>
                    <span class="name"><?= e($barber['display_name']) ?></span>
                    <span class="occ"><?= e($column['occupancy']) ?>%</span>
                </div>

                <div class="agenda-col-body">
                    <?php foreach ($column['timeline'] as $item): ?>
                        <?php if ($item['type'] === 'booking'): ?>
                            <?php
                                $booking    = $item['booking'];
                                $showBarber = false;
                                require View::path('components.booking-row');
                            ?>
                        <?php elseif ($item['type'] === 'block'): ?>
                            <div class="slot-row is-block">
                                <div class="slot-time"><?= e($item['start_label']) ?><small><?= e($item['end_label']) ?></small></div>
                                <div class="slot-info">
                                    <div class="who"><?= e(BlockedTime::typeLabel($item['block']['type'])) ?></div>
                                    <?php if (!empty($item['block']['reason'])): ?>
                                        <div class="what"><?= e($item['block']['reason']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($item['minutes'] >= 15): ?>
                            <div class="slot-row is-free">
                                <div class="slot-time"><?= e($item['start_label']) ?><small><?= e($item['end_label']) ?></small></div>
                                <div class="slot-info">
                                    <div class="what muted">Disponible · <?= (int) $item['minutes'] ?> min</div>
                                </div>
                                <div class="slot-side">
                                    <a href="<?= e(url('recepcion/reservas/nueva')) ?>" class="btn btn-xs btn-ghost">+ Agendar</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($column['timeline'] === []): ?>
                        <p class="small muted center" style="padding:20px 0">Sin jornada este día</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php View::stop(); ?>
