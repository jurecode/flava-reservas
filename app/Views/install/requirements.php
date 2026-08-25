<?php
/**
 * Ruta: /app/Views/install/requirements.php
 * PASO 1 — Requisitos del servidor.
 */

use Core\View;

View::layout('install');
View::start('content');

$critical = array_filter($result['checks'], static fn (array $c): bool => $c['critical']);
$optional = array_filter($result['checks'], static fn (array $c): bool => !$c['critical']);
?>

<div class="install-head">
    <h1>Vamos a instalar Flava Studio</h1>
    <p>Primero revisamos que tu servidor tenga todo lo necesario. Toma unos segundos.</p>
</div>

<div class="card mb-2">
    <h2 style="font-size:1rem">Obligatorio</h2>

    <div class="check-list">
        <?php foreach ($critical as $check): ?>
            <div class="check-item <?= $check['ok'] ? 'is-ok' : 'is-fail' ?>">
                <span class="mark"><?= $check['ok'] ? icon('check-circle', 17) : icon('x-circle', 17) ?></span>
                <div class="grow">
                    <div class="check-label"><?= e($check['label']) ?></div>
                    <div class="detail"><?= e($check['detail']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card mb-2">
    <h2 style="font-size:1rem">Opcional</h2>
    <p class="small muted" style="margin-top:-4px">Sin esto el sistema funciona; sólo se desactivan funciones puntuales.</p>

    <div class="check-list">
        <?php foreach ($optional as $check): ?>
            <div class="check-item <?= $check['ok'] ? 'is-ok' : 'is-warn' ?>">
                <span class="mark"><?= $check['ok'] ? icon('check-circle', 17) : icon('minus', 17) ?></span>
                <div class="grow">
                    <div class="check-label"><?= e($check['label']) ?></div>
                    <div class="detail"><?= e($check['detail']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$result['ok']): ?>
    <div class="alert alert-error">
        <?= icon('alert', 17) ?>
        <div>
            <strong>Falta resolver algo antes de seguir.</strong>
            En Hostinger la mayoría se arregla en <strong>hPanel → Avanzado → Configuración PHP</strong>.
            Corrige y vuelve a cargar esta página.
        </div>
    </div>
<?php endif; ?>

<div class="install-nav">
    <a href="<?= e(url('instalar/requisitos')) ?>" class="btn btn-ghost">
        <?= icon('refresh', 15) ?> Volver a comprobar
    </a>

    <?php if ($result['ok']): ?>
        <a href="<?= e(url('instalar/base-de-datos')) ?>" class="btn btn-primary btn-lg">
            Continuar <?= icon('arrow-right', 16) ?>
        </a>
    <?php else: ?>
        <button class="btn btn-primary btn-lg" disabled>Continuar</button>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
