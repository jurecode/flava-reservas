<?php
/**
 * Ruta: /app/Views/components/flash.php
 * Mensajes flash de sesión, con ícono SVG según el tono.
 */

use App\Support\Icon;
use Core\Session;

foreach (['success', 'error', 'warning', 'info'] as $type):
    if (!Session::hasFlash($type)) {
        continue;
    }
    $message = Session::getFlash($type);
?>
    <div class="alert alert-<?= e($type) ?>" role="alert">
        <?= icon(Icon::forFlash($type), 17) ?>
        <div><?= e($message) ?></div>
    </div>
<?php endforeach; ?>
