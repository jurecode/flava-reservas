<?php
/**
 * Ruta: /diagnostico.php
 *
 * Informe del servidor, independiente de la aplicación.
 *
 * Sirve cuando el sistema no arranca y hay que averiguar por qué: no carga el
 * framework, así que funciona aunque algo esté roto.
 *
 * SEGURIDAD: se niega a ejecutarse cuando el sistema ya está instalado, no
 * imprime contraseñas y recuerda borrarlo al terminar.
 */

declare(strict_types=1);

$raiz = __DIR__;

/**
 * Esta página existe mientras el sistema no funciona.
 *
 * Se desactiva cuando la instalación está completa Y la base de datos responde:
 * un sistema sano no debe exponer un informe del servidor. Pero si el archivo
 * de instalación está y la base NO conecta —copia incompleta, credenciales de
 * otro entorno—, justamente ahí es cuando hace falta el diagnóstico.
 */
if (is_file($raiz . '/config/installed.php') && baseDeDatosResponde($raiz)) {
    http_response_code(404);
    exit('No encontrado.');
}

/** ¿La base configurada acepta conexiones? */
function baseDeDatosResponde(string $raiz): bool
{
    $archivo = $raiz . '/config/database.php';

    if (!is_file($archivo)) {
        return false;
    }

    $cfg = cargarConfiguracionBd($archivo);

    if (!is_array($cfg) || ($cfg['database'] ?? '') === '') {
        return false;
    }

    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s',
                $cfg['host'] ?? 'localhost', $cfg['port'] ?? '3306', $cfg['database']),
            (string) ($cfg['username'] ?? ''),
            (string) ($cfg['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Lee config/database.php sin cargar el framework.
 *
 * El archivo puede usar env(); como aquí no existe esa función, se define una
 * versión mínima que devuelve el valor por defecto. Así el diagnóstico funciona
 * con un archivo generado por el instalador (valores literales) o con uno de
 * desarrollo (basado en env).
 */
function cargarConfiguracionBd(string $archivo): mixed
{
    if (!function_exists('env')) {
        function env(string $clave, mixed $porDefecto = null): mixed
        {
            $valor = $_ENV[$clave] ?? $_SERVER[$clave] ?? getenv($clave);

            return ($valor === false || $valor === null || $valor === '') ? $porDefecto : $valor;
        }
    }

    try {
        return @include $archivo;
    } catch (Throwable) {
        return null;
    }
}

// -----------------------------------------------------------------------
//  Comprobaciones
// -----------------------------------------------------------------------
$bloques = [];

// --- PHP ---
$phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$filas = [['Versión de PHP', $phpOk, PHP_VERSION . ($phpOk ? '' : ' · se necesita 8.1 o superior')]];

foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true,
          'curl' => false, 'gd' => false, 'openssl' => false, 'sodium' => false, 'zip' => false] as $ext => $obligatoria) {
    $cargada = extension_loaded($ext);
    $filas[] = [
        'Extensión ' . $ext . ($obligatoria ? '' : ' (opcional)'),
        $cargada || !$obligatoria,
        $cargada ? 'disponible' : ($obligatoria ? 'FALTA · actívala en hPanel → Configuración PHP' : 'no instalada'),
    ];
}

$filas[] = ['Memoria disponible', true, (string) ini_get('memory_limit')];
$filas[] = ['Tiempo máx. de ejecución', true, ini_get('max_execution_time') . 's'];

$deshabilitadas = trim((string) ini_get('disable_functions'));
$filas[] = ['Funciones deshabilitadas', true, $deshabilitadas === '' ? 'ninguna' : $deshabilitadas];

$bloques['PHP'] = $filas;

// --- Archivos ---
$filas = [];
foreach (['app', 'core', 'config', 'public', 'routes', 'database', 'storage'] as $carpeta) {
    $existe  = is_dir($raiz . '/' . $carpeta);
    $filas[] = ['Carpeta /' . $carpeta, $existe, $existe ? 'presente' : 'FALTA · la subida quedó incompleta'];
}

foreach (['.htaccess' => true, 'index.php' => true,
          'public/index.php' => true, 'core/bootstrap.php' => true,
          'database/flava.sql' => true] as $archivo => $obligatorio) {
    $existe  = is_file($raiz . '/' . $archivo);
    $filas[] = ['Archivo ' . $archivo, $existe, $existe ? 'presente' : 'FALTA'];
}

$bloques['Archivos del proyecto'] = $filas;

// --- Escritura ---
$filas = [];
foreach (['config', 'storage', 'storage/logs', 'storage/cache',
          'storage/backups', 'storage/framework', 'public/uploads'] as $carpeta) {
    $ruta = $raiz . '/' . $carpeta;

    if (!is_dir($ruta)) {
        @mkdir($ruta, 0775, true);
    }

    $escribible = is_dir($ruta) && is_writable($ruta);
    $permisos   = is_dir($ruta) ? substr(sprintf('%o', fileperms($ruta)), -3) : '—';

    // Prueba real: crear y borrar un archivo
    $pruebaReal = false;
    if ($escribible) {
        $tmp = $ruta . '/.prueba-' . bin2hex(random_bytes(3));
        $pruebaReal = @file_put_contents($tmp, 'x') !== false;
        @unlink($tmp);
    }

    $filas[] = [
        '/' . $carpeta,
        $pruebaReal,
        $pruebaReal
            ? 'escritura OK (' . $permisos . ')'
            : 'SIN ESCRITURA (' . $permisos . ') · permisos 755 o Corregir la propiedad de los archivos',
    ];
}

$bloques['Permisos de escritura'] = $filas;

// --- Sesiones ---
$rutaSesion = session_save_path();
$sesionOk   = false;
$detalle    = '';

if ($rutaSesion !== '' && is_dir($rutaSesion) && is_writable($rutaSesion)) {
    $sesionOk = true;
    $detalle  = 'directorio del sistema: ' . $rutaSesion;
} else {
    $propio = $raiz . '/storage/framework/sessions';
    if (!is_dir($propio)) {
        @mkdir($propio, 0775, true);
    }
    if (is_dir($propio) && is_writable($propio)) {
        $sesionOk = true;
        $detalle  = 'se usará storage/framework/sessions (el del sistema no sirve)';
    } else {
        $detalle  = 'NINGÚN directorio de sesiones escribible · ésta sería la causa del error 500';
    }
}

$bloques['Sesiones'] = [['Directorio de sesiones', $sesionOk, $detalle]];

// --- Base de datos ---
$filas   = [];
$archivo = $raiz . '/config/database.php';

if (!is_file($archivo)) {
    $filas[] = ['config/database.php', false, 'todavía no existe · lo crea el asistente en /instalar'];
} else {
    $cfg = cargarConfiguracionBd($archivo);

    if (!is_array($cfg)) {
        $filas[] = ['config/database.php', false, 'el archivo existe pero no devuelve configuración válida'];
    } else {
        $filas[] = ['Base de datos configurada', true, (string) ($cfg['database'] ?? '?')];
        $filas[] = ['Usuario', true, (string) ($cfg['username'] ?? '?')];
        $filas[] = ['Host', true, (string) ($cfg['host'] ?? '?')];

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $cfg['host'] ?? 'localhost', $cfg['port'] ?? '3306', $cfg['database'] ?? ''),
                (string) ($cfg['username'] ?? ''),
                (string) ($cfg['password'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6]
            );

            $tablas  = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $filas[] = ['Conexión', true, 'correcta · MySQL ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ' · ' . $tablas . ' tabla(s)'];
        } catch (Throwable $e) {
            $filas[] = ['Conexión', false, mb_substr($e->getMessage(), 0, 200)];
        }
    }
}

// Estado incoherente: marcado como instalado pero sin base utilizable
if (is_file($raiz . '/config/installed.php')) {
    $filas[] = [
        'config/installed.php',
        false,
        'EXISTE pero la base no responde · este archivo marca la instalación como '
        . 'terminada y desactiva el asistente. Si viene de otro equipo, BÓRRALO junto '
        . 'con config/database.php y vuelve a entrar a /instalar',
    ];
}

$bloques['Base de datos'] = $filas;

// --- Últimas líneas del log ---
$log     = '';
$archivos = glob($raiz . '/storage/logs/*.log') ?: [];
rsort($archivos);

if ($archivos !== []) {
    $contenido = (string) @file_get_contents($archivos[0]);
    $lineas    = array_slice(array_filter(explode("\n", $contenido)), -25);
    $log       = implode("\n", $lineas);
}

// -----------------------------------------------------------------------
//  Salida
// -----------------------------------------------------------------------
$totalFallos = 0;
foreach ($bloques as $filas) {
    foreach ($filas as [, $ok, ]) {
        if (!$ok) {
            $totalFallos++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Diagnóstico · Flava Studio</title>
<style>
    *,*::before,*::after{box-sizing:border-box}
    body{margin:0;background:#0D0D0D;color:#FFFDF5;padding:30px 18px;
         font:14px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
    .wrap{max-width:760px;margin:0 auto}
    .brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:1.05rem;margin-bottom:22px}
    .brand em{font-style:normal;color:#FFC400}
    h1{font-size:1.5rem;font-weight:800;margin:0 0 6px}
    .lead{color:rgba(255,253,245,.6);margin:0 0 22px}
    .card{background:#181818;border-radius:6px;padding:18px;margin-bottom:12px}
    .card h2{font-size:.95rem;margin:0 0 12px;color:#FFC400}
    .row{display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06)}
    .row:last-child{border-bottom:none}
    .mk{flex-shrink:0;width:16px;height:16px;margin-top:2px}
    .ok{color:#51CF66}.bad{color:#FF6B6B}
    .tx{flex:1;min-width:0}
    .tx strong{display:block;font-size:.86rem;font-weight:600}
    .tx span{color:rgba(255,253,245,.5);font-size:.79rem;word-break:break-word}
    pre{background:#0A0A0A;color:#9BB89E;padding:14px;border-radius:6px;font-size:.72rem;
        line-height:1.6;overflow:auto;max-height:340px;white-space:pre-wrap;word-break:break-word;margin:0}
    .aviso{background:rgba(255,196,0,.1);border-left:3px solid #FFC400;padding:13px 15px;
           border-radius:6px;margin:20px 0;font-size:.86rem}
    .resumen{padding:14px 16px;border-radius:6px;margin-bottom:20px;font-weight:600}
    .resumen-ok{background:rgba(81,207,102,.13);color:#B2F2BB}
    .resumen-bad{background:rgba(255,107,107,.13);color:#FFC9C9}
    code{background:rgba(255,196,0,.13);color:#FFC400;padding:1px 5px;border-radius:3px;
         font-family:ui-monospace,Menlo,monospace;font-size:.85em}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <svg width="24" height="27" viewBox="0 0 60 68" fill="none">
            <path d="M30 2 55 16.5v29L30 60 5 45.5v-29z" fill="#FFC400"/>
            <path d="M30 9.5 48.5 20v22L30 52.5 11.5 42V20z" fill="#181818"/>
            <path d="M22 26h16M20.5 31h19M22 36h16" stroke="#FFC400" stroke-width="2.6" stroke-linecap="round"/>
        </svg>
        <span>FLAVA <em>STUDIO</em></span>
    </div>

    <h1>Diagnóstico del servidor</h1>
    <p class="lead">Informe completo, sin depender de que la aplicación arranque.</p>

    <div class="resumen <?= $totalFallos === 0 ? 'resumen-ok' : 'resumen-bad' ?>">
        <?= $totalFallos === 0
            ? 'Todo en orden. Puedes continuar en /instalar'
            : $totalFallos . ' comprobación(es) con problemas · están marcadas abajo' ?>
    </div>

    <?php foreach ($bloques as $titulo => $filas): ?>
        <div class="card">
            <h2><?= htmlspecialchars($titulo, ENT_QUOTES) ?></h2>
            <?php foreach ($filas as [$etiqueta, $ok, $detalle]): ?>
                <div class="row">
                    <svg class="mk <?= $ok ? 'ok' : 'bad' ?>" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <?php if ($ok): ?><path d="M20 6L9 17l-5-5"/><?php else: ?><path d="M18 6L6 18M6 6l12 12"/><?php endif; ?>
                    </svg>
                    <div class="tx">
                        <strong><?= htmlspecialchars($etiqueta, ENT_QUOTES) ?></strong>
                        <span><?= htmlspecialchars((string) $detalle, ENT_QUOTES) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="card">
        <h2>Últimas líneas del log</h2>
        <?php if ($log === ''): ?>
            <p style="color:rgba(255,253,245,.5);font-size:.85rem;margin:0">
                No hay archivos de log todavía. Si el sitio ya dio error, esto significa
                que el sistema tampoco pudo escribir el log: revisa los permisos de
                <code>storage/logs</code>.
            </p>
        <?php else: ?>
            <pre><?= htmlspecialchars($log, ENT_QUOTES) ?></pre>
        <?php endif; ?>
    </div>

    <div class="aviso">
        <strong>Borra este archivo cuando termines.</strong>
        Muestra información del servidor que no conviene dejar pública. Se desactiva
        solo al completar la instalación, pero es mejor eliminarlo.
    </div>
</div>
</body>
</html>
