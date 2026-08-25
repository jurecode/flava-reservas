<?php
/**
 * Ruta: /index.php
 *
 * FRONT CONTROLLER PARA HOSTING COMPARTIDO.
 *
 * Cuando el dominio apunta a la carpeta del proyecto —y no se puede cambiar el
 * directorio raíz desde el panel— la aplicación se sirve desde aquí.
 *
 * Se evita a propósito la técnica de «reescribir todo hacia /public»: como
 * REQUEST_URI no cambia en las reescrituras internas, esa regla se reaplica en
 * algunos servidores y termina en un bucle que el servidor corta dejando de
 * aplicar el .htaccess. En su lugar, el front controller vive en la raíz y sólo
 * /assets y /uploads se reescriben hacia /public.
 *
 * Si el proyecto está incompleto o mal subido, en vez de un error críptico se
 * muestra una pantalla de diagnóstico con la solución.
 */

declare(strict_types=1);

$root = __DIR__;

// ---------------------------------------------------------------------
//  Servir directamente los archivos estáticos si no hubo reescritura
// ---------------------------------------------------------------------
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// En modo raíz los enlaces apuntan a /public/assets/... (rutas reales, que el
// servidor entrega solo). Esta rama es la red de seguridad para servidores que
// hacen pasar todas las peticiones por el front controller.
if (preg_match('#^/(?:public/)?(assets|uploads)/#', $uri)) {
    $relativa = str_starts_with($uri, '/public/') ? substr($uri, 7) : $uri;
    $file     = realpath($root . '/public' . $relativa);
    $base     = realpath($root . '/public');

    if ($file !== false && $base !== false && str_starts_with($file, $base . DIRECTORY_SEPARATOR) && is_file($file)) {
        $tipos = [
            'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif', 'ico' => 'image/x-icon',
            'woff2' => 'font/woff2', 'woff' => 'font/woff',
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        // Nunca se sirve nada ejecutable, aunque esté dentro de /public
        if (isset($tipos[$extension])) {
            header('Content-Type: ' . $tipos[$extension]);
            header('Cache-Control: public, max-age=2592000');
            header('X-Content-Type-Options: nosniff');
            readfile($file);
            exit;
        }
    }

    http_response_code(404);
    exit;
}

// ---------------------------------------------------------------------
//  Arrancar la aplicación
// ---------------------------------------------------------------------
$bootstrap = $root . '/core/bootstrap.php';

if (is_file($bootstrap) && is_file($root . '/public/index.php')) {
    // Los assets viven en /public: los enlaces deben incluir ese prefijo.
    define('FLAVA_ENTRY', 'root');

    /** @var Core\App $app */
    $app = require $bootstrap;
    $app->boot()->run();
    exit;
}

// ---------------------------------------------------------------------
//  El proyecto no está completo: diagnóstico
// ---------------------------------------------------------------------
$comprobaciones = [
    ['Carpeta /core', is_dir($root . '/core'), 'Contiene el núcleo del sistema'],
    ['Carpeta /public', is_dir($root . '/public'), 'Contiene los archivos públicos'],
    ['Carpeta /app', is_dir($root . '/app'), 'Contiene la aplicación'],
    ['PHP 8.1 o superior', version_compare(PHP_VERSION, '8.1.0', '>='), 'Este servidor usa PHP ' . PHP_VERSION],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Flava Studio · Instalación incompleta</title>
<style>
    *,*::before,*::after{box-sizing:border-box}
    body{margin:0;background:#0D0D0D;color:#FFFDF5;
         font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
         padding:32px 18px;
         background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='96' viewBox='0 0 56 96'%3E%3Cpath d='M28 0l24 14v28L28 56 4 42V14z' fill='none' stroke='%23FFC400' stroke-opacity='.06' stroke-width='1.3'/%3E%3C/svg%3E")}
    .wrap{max-width:600px;margin:0 auto}
    .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:1.1rem;margin-bottom:26px}
    .brand em{font-style:normal;color:#FFC400}
    h1{font-size:1.55rem;font-weight:800;letter-spacing:-.02em;margin:0 0 8px}
    .lead{color:rgba(255,253,245,.66);margin:0 0 24px}
    .card{background:#181818;border-radius:8px;padding:20px;margin-bottom:14px}
    .card h2{font-size:1rem;margin:0 0 14px}
    .row{display:flex;gap:11px;align-items:flex-start;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.07)}
    .row:last-child{border-bottom:none}
    .mk{flex-shrink:0;width:18px;height:18px;margin-top:1px}
    .tx strong{display:block;font-size:.9rem}
    .tx span{color:rgba(255,253,245,.55);font-size:.81rem}
    .ok{color:#51CF66}.bad{color:#FF6B6B}
    ol{padding-left:20px;margin:0}
    ol li{margin-bottom:9px;font-size:.89rem;color:rgba(255,253,245,.78)}
    code{background:rgba(255,196,0,.13);color:#FFC400;padding:2px 6px;border-radius:3px;
         font-family:ui-monospace,Menlo,monospace;font-size:.85em}
    .foot{color:rgba(255,253,245,.36);font-size:.78rem;text-align:center;margin-top:26px}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <svg width="26" height="30" viewBox="0 0 60 68" fill="none" aria-hidden="true">
            <path d="M30 2 55 16.5v29L30 60 5 45.5v-29z" fill="#FFC400"/>
            <path d="M30 9.5 48.5 20v22L30 52.5 11.5 42V20z" fill="#181818"/>
            <path d="M22 26h16M20.5 31h19M22 36h16" stroke="#FFC400" stroke-width="2.6" stroke-linecap="round"/>
        </svg>
        <span>FLAVA <em>STUDIO</em></span>
    </div>

    <h1>Faltan archivos del proyecto</h1>
    <p class="lead">
        Este archivo se está ejecutando, pero no encuentra el resto del sistema.
        Lo más probable es que la subida quedara incompleta.
    </p>

    <div class="card">
        <h2>Diagnóstico</h2>
        <?php foreach ($comprobaciones as [$titulo, $ok, $detalle]): ?>
            <div class="row">
                <svg class="mk <?= $ok ? 'ok' : 'bad' ?>" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php if ($ok): ?>
                        <circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>
                    <?php else: ?>
                        <circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>
                    <?php endif; ?>
                </svg>
                <div class="tx">
                    <strong><?= htmlspecialchars($titulo, ENT_QUOTES) ?></strong>
                    <span><?= htmlspecialchars($detalle, ENT_QUOTES) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2>Qué hacer</h2>
        <ol>
            <li>Vuelve a subir el proyecto completo a <code>public_html</code>.</li>
            <li>Comprueba que estén las carpetas <code>app</code>, <code>core</code>,
                <code>config</code>, <code>public</code>, <code>routes</code>,
                <code>database</code> y <code>storage</code>.</li>
            <li>Si subiste un <code>.zip</code>, descomprímelo dentro de
                <code>public_html</code> y borra el comprimido.</li>
            <li>Recarga esta página.</li>
        </ol>
    </div>

    <p class="foot">Guía completa en <code>docs/HOSTINGER.md</code></p>
</div>
</body>
</html>
