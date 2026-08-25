<?php
/**
 * Ruta: /app/Views/barber/agenda/index.php
 * Panel del barbero (spec §17). Simple y directo: sólo lo que necesita.
 */

use App\Models\BlockedTime;
use App\Support\BookingStatus;
use App\Support\PaymentStatus;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Hola, <?= e(explode(' ', $barber['display_name'])[0]) ?></h1>
        <p class="sub"><?= e($dateLabel) ?> · <?= (int) $stats['total'] ?> cita(s)</p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('barbero/agenda?date=' . $prev)) ?>" class="btn btn-light btn-sm">‹</a>
        <a href="<?= e(url('barbero/agenda?date=' . today())) ?>" class="btn btn-light btn-sm">Hoy</a>
        <a href="<?= e(url('barbero/agenda?date=' . $next)) ?>" class="btn btn-light btn-sm">›</a>
    </div>
</div>

<div class="week-strip">
    <?php foreach ($week as $day): ?>
        <a href="<?= e(url('barbero/agenda?date=' . $day['date'])) ?>"
           class="week-day <?= $day['active'] ? 'is-active' : '' ?> <?= $day['is_today'] ? 'is-today' : '' ?>">
            <span class="dow"><?= e($day['label']) ?></span>
            <span class="num"><?= (int) $day['day'] ?></span>
            <span class="cnt"><?= $day['count'] > 0 ? $day['count'] . ' cita' . ($day['count'] > 1 ? 's' : '') : '—' ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-head">
            <span class="k">Citas del día</span>
            <span class="ico-box"><?= icon('calendar', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['total'] ?></div>
        <div class="d"><?= (int) $stats['upcoming'] ?> por atender</div>
    </div>
    <div class="kpi kpi-ok">
        <div class="kpi-head">
            <span class="k">Finalizadas</span>
            <span class="ico-box"><?= icon('check-circle', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['completed'] ?></div>
        <div class="d"><?= e(money($stats['revenue'])) ?> cobrados</div>
    </div>
    <div class="kpi kpi-info">
        <div class="kpi-head">
            <span class="k">Ocupación</span>
            <span class="ico-box"><?= icon('pie-chart', 15) ?></span>
        </div>
        <div class="v"><?= e($stats['occupancy']) ?>%</div>
        <div class="d">de tu jornada</div>
    </div>
    <div class="kpi <?= (int) $stats['no_show'] > 0 ? 'kpi-danger' : '' ?>">
        <div class="kpi-head">
            <span class="k">Esta semana</span>
            <span class="ico-box"><?= icon('bar-chart', 15) ?></span>
        </div>
        <div class="v"><?= (int) $stats['week_total'] ?></div>
        <div class="d"><?= (int) $stats['no_show'] ?> no asistió hoy</div>
    </div>
</div>

<h2 style="font-size:1.1rem;margin-bottom:14px">Mi agenda</h2>

<div class="stack-sm">
    <?php if ($timeline === []): ?>
        <div class="card">
            <?php
                $icon = 'palm';
                $message = 'No tienes jornada este día';
                $hint = 'Si crees que es un error, avisa a administración.';
                require View::path('components.empty');
            ?>
        </div>
    <?php endif; ?>

    <?php foreach ($timeline as $item): ?>
        <?php if ($item['type'] === 'booking'): ?>
            <?php
                $booking  = $item['booking'];
                $customer = trim($booking['customer_first_name'] . ' ' . $booking['customer_last_name']);
                $states   = array_values(array_intersect(
                    BookingStatus::nextOptions((string) $booking['status']),
                    [BookingStatus::CHECKED_IN, BookingStatus::IN_PROGRESS, BookingStatus::COMPLETED, BookingStatus::NO_SHOW]
                ));
            ?>
            <div class="appt status-<?= e($booking['status']) ?>">
                <div class="appt-head">
                    <div class="slot-time">
                        <?= e(time_hm($booking['start_time'])) ?>
                        <small><?= e(time_hm($booking['end_time'])) ?></small>
                    </div>

                    <div class="slot-info">
                        <div class="who">
                            <a href="<?= e(url('barbero/clientes/' . $booking['customer_id'])) ?>"><?= e($customer) ?></a>
                            <?php if ((int) $booking['customer_visits'] === 0): ?>
                                <span class="badge badge-confirmed">Primera vez</span>
                            <?php elseif ((int) $booking['customer_no_shows'] >= 2): ?>
                                <span class="badge badge-noshow"><?= icon('alert', 11) ?><?= (int) $booking['customer_no_shows'] ?> no-show</span>
                            <?php endif; ?>
                        </div>

                        <div class="what">
                            <span><?= icon('scissors', 13) ?> <?= e($booking['service_name']) ?></span>
                            <span><?= icon('clock', 13) ?> <?= (int) $booking['duration_minutes'] ?> min</span>
                            <?php if (!empty($booking['customer_phone'])): ?>
                                <a href="tel:<?= e($booking['customer_phone']) ?>" class="muted">
                                    <?= icon('phone', 13) ?> <?= e(Str::phoneDisplay($booking['customer_phone'])) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="appt-status">
                        <span class="badge <?= e(BookingStatus::badgeClass($booking['status'])) ?>"><?= e(BookingStatus::label($booking['status'])) ?></span>
                        <?php if ($booking['payment_status'] === PaymentStatus::PAID): ?>
                            <span class="badge badge-paid"><?= icon('check', 11) ?>Pagado</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($booking['customer_notes'])): ?>
                    <div class="small muted mt-1"><?= icon('message', 13) ?> <?= e($booking['customer_notes']) ?></div>
                <?php endif; ?>

                <?php if (!empty($item['note'])): ?>
                    <div class="tech-note">
                        <strong><?= icon('note', 12) ?> Nota técnica</strong>
                        <?= e($item['note']['note']) ?>
                    </div>
                <?php endif; ?>

                <div class="appt-actions">
                    <?php
                        $action = url('barbero/reservas/' . $booking['id'] . '/estado');
                        if ($states !== []) {
                            require View::path('components.status-form');
                        }
                    ?>
                    <button type="button" class="btn btn-sm btn-ghost" data-note-toggle="<?= (int) $booking['id'] ?>">
                        <?= icon('note', 15) ?> Nota
                    </button>
                </div>

                <form method="post" action="<?= e(url('barbero/reservas/' . $booking['id'] . '/nota')) ?>"
                      class="hidden mt-2" data-note-form="<?= (int) $booking['id'] ?>" data-once>
                    <?= csrf_field() ?>
                    <textarea class="textarea mb-1" name="note" rows="2" required
                              placeholder="Fade bajo. Máquina #1 costados. Tijera arriba." style="min-height:62px"></textarea>
                    <div class="row-between">
                        <label class="check">
                            <input type="checkbox" name="pinned" value="1">
                            <span class="small">Fijar</span>
                        </label>
                        <button type="submit" class="btn btn-sm btn-dark">Guardar nota</button>
                    </div>
                </form>
            </div>

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
                    <div class="what muted">DISPONIBLE · <?= (int) $item['minutes'] ?> min</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php View::stop(); ?>

<?php View::start('scripts'); ?>
<script>
document.querySelectorAll('[data-note-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = document.querySelector('[data-note-form="' + button.dataset.noteToggle + '"]');
        form?.classList.toggle('hidden');
        form?.querySelector('textarea')?.focus();
    });
});
</script>
<?php View::stop(); ?>
