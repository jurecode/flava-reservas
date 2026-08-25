<?php
/**
 * Ruta: /app/Views/install/schema.php
 * PASO 3 — Creación de las tablas.
 */

use Core\View;

View::layout('install');
View::start('content');
?>

<div class="install-head">
    <h1>Estructura de la base de datos</h1>
    <p>Creamos las 24 tablas del sistema. Sólo se ejecuta si faltan: nunca borra datos existentes.</p>
</div>

<?php if ($status['installed']): ?>
    <div class="alert alert-success">
        <?= icon('check-circle', 17) ?>
        <div>
            <strong>Las tablas ya están creadas.</strong>
            Se detectaron <?= (int) $status['tables'] ?> tablas en la base de datos.
        </div>
    </div>

    <div class="install-nav">
        <a href="<?= e(url('instalar/base-de-datos')) ?>" class="btn btn-ghost"><?= icon('arrow-left', 15) ?> Atrás</a>
        <a href="<?= e(url('instalar/administrador')) ?>" class="btn btn-primary btn-lg">
            Continuar <?= icon('arrow-right', 16) ?>
        </a>
    </div>
<?php else: ?>
    <div class="card mb-2">
        <div class="data-row">
            <span class="k">Tablas encontradas</span>
            <span class="v"><?= (int) $status['tables'] ?></span>
        </div>
        <div class="data-row">
            <span class="k">Tablas por crear</span>
            <span class="v"><?= count($status['missing']) ?></span>
        </div>

        <?php if ($status['tables'] > 0): ?>
            <div class="alert alert-warning mt-2 mb-0">
                <?= icon('zap', 17) ?>
                <div>
                    La base tiene tablas pero le faltan algunas. Si vienes de una instalación
                    incompleta, lo más limpio es <strong>vaciar la base</strong> desde phpMyAdmin
                    y volver aquí.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($result) && !$result['ok']): ?>
        <div class="alert alert-error">
            <?= icon('alert', 17) ?>
            <div>
                <strong>No se pudieron crear las tablas.</strong><br>
                <?= e($result['message']) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:1rem">Opción A · Automática <span class="badge badge-confirmed">Recomendada</span></h2>
        <p class="small muted">Creamos todo desde aquí, sin que tengas que tocar phpMyAdmin.</p>

        <form method="post" action="<?= e(url('instalar/esquema')) ?>" data-once>
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block btn-lg">
                <?= icon('database', 16) ?> Crear las tablas ahora
            </button>
        </form>

        <hr class="divider">

        <h2 style="font-size:1rem">Opción B · Manual</h2>
        <p class="small muted mb-0">
            Si prefieres hacerlo tú: entra a <strong>phpMyAdmin</strong> desde el panel de tu hosting,
            selecciona tu base de datos, ve a <strong>Importar</strong> y sube el archivo
            <code>database/flava.sql</code> que viene con el proyecto. Luego recarga esta página.
        </p>
    </div>

    <div class="install-nav">
        <a href="<?= e(url('instalar/base-de-datos')) ?>" class="btn btn-ghost"><?= icon('arrow-left', 15) ?> Atrás</a>
        <a href="<?= e(url('instalar/esquema')) ?>" class="btn btn-ghost"><?= icon('refresh', 15) ?> Volver a comprobar</a>
    </div>
<?php endif; ?>

<?php View::stop(); ?>
