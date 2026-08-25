<?php
/**
 * Ruta: /app/Views/install/database.php
 * PASO 2 — Conexión con la base de datos que creaste en el panel del hosting.
 */

use Core\View;

View::layout('install');
View::start('content');
?>

<div class="install-head">
    <h1>Conecta la base de datos</h1>
    <p>La base la creas tú desde el panel de tu hosting. Aquí sólo la conectamos.</p>
</div>

<div class="hosting-hint">
    <?= icon('info', 16) ?>
    <div>
        <strong>Si usas Hostinger:</strong> entra a
        <code>hPanel → Bases de datos → Administración de bases de datos MySQL</code>,
        crea una base y un usuario, y <strong>asigna el usuario a la base</strong>.
        Hostinger antepone un prefijo, así que los nombres quedan tipo
        <code>u123456789_flava</code>. El host es <code>localhost</code>.
    </div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('instalar/base-de-datos')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="host">Host</label>
                <input class="input" type="text" id="host" name="host" required maxlength="120"
                       value="<?= e(old('host', $config['host'] ?? 'localhost')) ?>">
                <div class="field-hint">En hosting compartido casi siempre es <code>localhost</code>.</div>
            </div>

            <div class="field">
                <label class="label" for="port">Puerto</label>
                <input class="input" type="text" id="port" name="port" required maxlength="6"
                       value="<?= e(old('port', $config['port'] ?? '3306')) ?>">
            </div>
        </div>

        <div class="field">
            <label class="label" for="database">Nombre de la base de datos</label>
            <input class="input <?= error_for('database') ? 'is-invalid' : '' ?>" type="text" id="database" name="database"
                   required maxlength="120" placeholder="u123456789_flava" autocomplete="off"
                   value="<?= e(old('database', $config['database'] ?? '')) ?>">
            <?php if ($m = error_for('database')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
        </div>

        <div class="grid-2">
            <div class="field">
                <label class="label" for="username">Usuario</label>
                <input class="input" type="text" id="username" name="username" required maxlength="120"
                       placeholder="u123456789_admin" autocomplete="off"
                       value="<?= e(old('username', $config['username'] ?? '')) ?>">
            </div>

            <div class="field">
                <label class="label" for="password">Contraseña</label>
                <input class="input" type="password" id="password" name="password" autocomplete="new-password"
                       placeholder="••••••••">
                <div class="field-hint">La que definiste al crear el usuario MySQL.</div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <?= icon('database', 16) ?> Probar y guardar conexión
        </button>
    </form>
</div>

<?php if (!empty($test) && $test['ok']): ?>
    <div class="card card-outlined mt-2">
        <div class="check-item is-ok" style="border:none;padding:0">
            <span class="mark"><?= icon('check-circle', 17) ?></span>
            <div class="grow">
                <div class="check-label"><?= e($test['message']) ?></div>
                <div class="detail">
                    MySQL <?= e($test['server']) ?> ·
                    <?= (int) $test['tables'] ?> tabla(s) en la base
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($manual)): ?>
    <div class="card mt-2">
        <h2 style="font-size:1rem"><?= icon('file-text', 16) ?> Crea el archivo a mano</h2>
        <p class="small muted">
            La carpeta <code>/config</code> no permite escritura. Desde el
            <strong>Administrador de archivos</strong> de tu hosting crea el archivo
            <code>config/database.php</code> y pega exactamente esto:
        </p>

        <pre class="code-block" id="dbConfig"><?= e($manual) ?></pre>

        <div class="row gap-sm mt-2">
            <button type="button" class="btn btn-light btn-sm" data-copy="<?= e($manual) ?>">
                <?= icon('copy', 15) ?> Copiar contenido
            </button>
            <a href="<?= e(url('instalar/base-de-datos/confirmar')) ?>" class="btn btn-primary btn-sm">
                Ya lo creé, continuar <?= icon('arrow-right', 15) ?>
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="install-nav">
    <a href="<?= e(url('instalar/requisitos')) ?>" class="btn btn-ghost">
        <?= icon('arrow-left', 15) ?> Atrás
    </a>

    <?php if (!empty($test) && $test['ok']): ?>
        <a href="<?= e(url('instalar/esquema')) ?>" class="btn btn-primary btn-lg">
            Continuar <?= icon('arrow-right', 16) ?>
        </a>
    <?php endif; ?>
</div>

<?php View::stop(); ?>
