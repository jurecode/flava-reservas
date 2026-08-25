<?php
/**
 * Ruta: /app/Views/booking/lookup.php
 * Consulta de reserva con código + email (spec §29).
 */

use Core\View;

View::layout('site');
View::start('content');
?>

<section class="section">
    <div class="container container-sm">
        <div class="section-head center">
            <span class="eyebrow">Sin cuenta, sin complicaciones</span>
            <h1 style="font-size:1.9rem">Consultar mi reserva</h1>
            <p>Ingresa el código que te entregamos y el email con el que reservaste.</p>
        </div>

        <div class="card">
            <form method="post" action="<?= e(url('mi-reserva')) ?>" data-once>
                <?= csrf_field() ?>

                <div class="field">
                    <label class="label" for="code">Código de reserva</label>
                    <input class="input mono" type="text" id="code" name="code" required
                           value="<?= e(old('code')) ?>" placeholder="FLV-260824-A7C2"
                           style="text-transform:uppercase" maxlength="24" autocomplete="off">
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" required
                           value="<?= e(old('email')) ?>" placeholder="tu@email.cl" autocomplete="email">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Buscar mi reserva</button>
            </form>
        </div>

        <p class="center small muted mt-3">
            ¿Perdiste el código? Búscalo en el correo de confirmación o escríbenos por WhatsApp.
        </p>
    </div>
</section>

<?php View::stop(); ?>
