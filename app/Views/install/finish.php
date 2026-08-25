<?php
/**
 * Ruta: /app/Views/install/finish.php
 * PASO 6 — Cierre del instalador.
 */

use Core\View;

View::layout('install');
View::start('content');
?>

<?php if (!$verification['ok']): ?>
    <div class="install-head">
        <h1>Casi listo</h1>
        <p>Quedan un par de cosas por resolver antes de cerrar la instalación.</p>
    </div>

    <div class="alert alert-error">
        <?= icon('alert', 17) ?>
        <div>
            <strong>Revisa esto:</strong>
            <ul style="margin:6px 0 0;padding-left:18px">
                <?php foreach ($verification['problems'] as $problem): ?>
                    <li><?= e($problem) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="install-nav">
        <a href="<?= e(url('instalar/requisitos')) ?>" class="btn btn-ghost">Volver al inicio</a>
        <a href="<?= e(url('instalar/finalizar')) ?>" class="btn btn-primary"><?= icon('refresh', 15) ?> Comprobar de nuevo</a>
    </div>
<?php else: ?>
    <div class="install-done">
        <div class="install-done-mark"><?= icon('check', 28) ?></div>
        <h1 style="font-size:1.7rem;margin-bottom:6px">Todo listo</h1>
        <p class="muted">Flava Studio quedó instalado y verificado.</p>
    </div>

    <div class="card mb-2">
        <h2 style="font-size:1rem">Lo que sigue</h2>

        <div class="next-steps">
            <div class="next-step">
                <span class="num">1</span>
                <div class="txt">
                    <strong>Crea tus barberos y define sus horarios</strong>
                    <span>Administración → Barberos. Sin horario no hay disponibilidad que mostrar.</span>
                </div>
            </div>

            <div class="next-step">
                <span class="num">2</span>
                <div class="txt">
                    <strong>Revisa los servicios y sus precios</strong>
                    <span>Vienen seis de ejemplo. Ajústalos, súbeles foto y marca quién los realiza.</span>
                </div>
            </div>

            <div class="next-step">
                <span class="num">3</span>
                <div class="txt">
                    <strong>Ajusta las reglas de reserva</strong>
                    <span>Administración → Configuración: anticipación mínima, intervalo y políticas.</span>
                </div>
            </div>

            <div class="next-step">
                <span class="num">4</span>
                <div class="txt">
                    <strong>Haz una reserva de prueba</strong>
                    <span>Entra al sitio como cliente y reserva. Es la mejor forma de verificar todo.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outlined mb-2">
        <h2 style="font-size:1rem"><?= icon('shield', 16) ?> Antes de publicar</h2>
        <p class="small muted mb-0">
            Al pulsar el botón se crea <code>/config/installed.php</code> y el asistente
            queda cerrado: nadie podrá relanzarlo sobre tus datos. Si algún día necesitas
            reinstalar desde cero, basta con borrar ese archivo.
        </p>
    </div>

    <form method="post" action="<?= e(url('instalar/finalizar')) ?>" data-once>
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <?= icon('lock', 16) ?> Cerrar el instalador e ir al panel
        </button>
    </form>
<?php endif; ?>

<?php View::stop(); ?>
