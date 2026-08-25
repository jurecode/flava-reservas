<?php
/**
 * Ruta: /app/Views/barber/customers/index.php
 * Clientes atendidos por este barbero (spec §18).
 */

use App\Support\Str;
use Core\View;

View::layout('panel');
View::start('content');
?>

<div class="page-head">
    <div>
        <h1>Mis clientes</h1>
        <p class="sub">Personas que has atendido · <?= count($customers) ?> registro(s)</p>
    </div>
</div>

<form method="get" class="filters">
    <div class="filters-row">
        <div class="field">
            <label class="label" for="q">Buscar</label>
            <input class="input" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Nombre o teléfono">
        </div>
        <div class="row gap-sm">
            <button type="submit" class="btn btn-dark btn-sm">Buscar</button>
            <a href="<?= e(url('barbero/clientes')) ?>" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </div>
</form>

<?php if ($customers === []): ?>
    <div class="card">
        <?php
            $icon = 'user';
            $message = 'Todavía no registras atenciones';
            $hint = 'Cuando finalices servicios, tus clientes aparecerán aquí.';
            require View::path('components.empty');
        ?>
    </div>
<?php else: ?>
    <div class="grid-3">
        <?php foreach ($customers as $customer): ?>
            <a href="<?= e(url('barbero/clientes/' . $customer['id'])) ?>" class="card card-hover">
                <div class="row row-nowrap gap-sm">
                    <span class="avatar"><?= e(Str::initials($customer['first_name'], $customer['last_name'])) ?></span>
                    <div class="grow">
                        <strong><?= e(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></strong>
                        <div class="small muted">
                            <?= (int) $customer['visits'] ?> visita(s)
                            <?php if (!empty($customer['last_visit'])): ?>
                                · última <?= e(\App\Support\DateHelper::shortEs($customer['last_visit'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php View::stop(); ?>
