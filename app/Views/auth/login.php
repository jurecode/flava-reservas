<?php
/**
 * Ruta: /app/Views/auth/login.php
 */

use Core\View;

View::layout('blank');
View::start('content');
?>
<div class="auth-shell">
    <div class="w-full" style="max-width:410px">
        <div class="auth-card">
            <div class="auth-brand">
                <img src="<?= e(asset('images/flava-mark.svg')) ?>" alt="" class="mark" width="54" height="61">
                <h1>FLAVA <em>STUDIO</em></h1>
                <p>Acceso del equipo</p>
            </div>

            <?php require View::path('components.flash'); ?>

            <form method="post" action="<?= e(url('login')) ?>" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                           value="<?= e(old('email')) ?>" required autocomplete="username" autofocus placeholder="tu@flava.cl">
                    <?php if ($m = error_for('email')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="password">Contraseña</label>
                    <input class="input <?= error_for('password') ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
                           required autocomplete="current-password" placeholder="••••••••">
                    <?php if ($m = error_for('password')): ?><div class="field-error"><?= e($m) ?></div><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">Ingresar</button>
            </form>

            <div class="center mt-3">
                <a href="<?= e(url('recuperar')) ?>" class="small muted">¿Olvidaste tu contraseña?</a>
            </div>
        </div>

        <p class="center small mt-3">
            <a href="<?= e(url('/')) ?>" class="muted">← Volver al sitio</a>
        </p>
    </div>
</div>
<?php View::stop(); ?>
