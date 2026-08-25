<?php
/**
 * Ruta: /app/Views/barber/customers/show.php
 * Ficha reducida: sólo lo pertinente para atender (sin RUT, email ni montos).
 */

use App\Support\BookingStatus;
use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');

$name = trim($customer['first_name'] . ' ' . $customer['last_name']);
?>

<div class="page-head">
    <div class="row row-nowrap gap-sm">
        <span class="avatar avatar-lg"><?= e(Str::initials($customer['first_name'], $customer['last_name'])) ?></span>
        <div>
            <h1><?= e($name) ?></h1>
            <p class="sub">
                <?= (int) $customer['completed_bookings'] ?> visita(s)
                <?php if ($lastVisit !== null): ?> · última: <?= e($lastVisit) ?><?php endif; ?>
                <?php if ((int) $customer['no_show_count'] >= 2): ?>
                    · <span class="badge badge-noshow"><?= (int) $customer['no_show_count'] ?> inasistencias</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="page-actions">
        <?php if (!empty($customer['phone'])): ?>
            <a href="tel:<?= e($customer['phone']) ?>" class="btn btn-light btn-sm"><?= e(Str::phoneDisplay($customer['phone'])) ?></a>
            <?php if ($whatsapp = Str::whatsappLink($customer['whatsapp_phone'] ?: $customer['phone'], 'Hola ' . $customer['first_name'] . ' 👋')): ?>
                <a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">WhatsApp</a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= e(url('barbero/clientes')) ?>" class="btn btn-ghost btn-sm">← Volver</a>
    </div>
</div>

<div class="grid-2 gap-lg">
    <div class="card">
        <h2 style="font-size:1rem">Notas técnicas</h2>
        <p class="small muted">Cómo le gusta el corte, preferencias y detalles a recordar.</p>

        <?php if ($notes === []): ?>
            <p class="small muted mb-0 mt-2">Sin notas registradas. Agrega una desde tu agenda al terminar la atención.</p>
        <?php else: ?>
            <div class="stack-sm mt-2">
                <?php foreach ($notes as $note): ?>
                    <div class="tech-note">
                        <strong>
                            <?php if ($note['is_pinned']): ?><?= icon('pin', 12) ?><?php endif; ?>
                            <?= e($note['author_first'] ?? 'Equipo') ?> · <?= e(\App\Support\DateHelper::shortEs($note['created_at'])) ?>
                        </strong>
                        <?= e($note['note']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card card-flush">
        <div class="card-head"><h2>Historial de atenciones</h2></div>
        <div class="card-body stack-sm" style="max-height:560px;overflow-y:auto">
            <?php if ($history === []): ?>
                <p class="small muted mb-0">Sin atenciones registradas.</p>
            <?php else: ?>
                <?php foreach ($history as $visit): ?>
                    <div class="row-between small" style="padding:8px 0;border-bottom:1px solid var(--line)">
                        <span>
                            <strong><?= e(\App\Support\DateHelper::shortEs($visit['booking_date'])) ?></strong>
                            · <?= e($visit['service_name']) ?>
                            <span class="muted">con <?= e($visit['barber_name']) ?></span>
                        </span>
                        <span class="badge <?= e(BookingStatus::badgeClass($visit['status'])) ?>"><?= e(BookingStatus::label($visit['status'])) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php View::stop(); ?>
