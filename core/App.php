<?php
/**
 * Ruta: /core/App.php
 * Kernel: arranque, resolución de la solicitud y manejo global de errores.
 */

namespace Core;

use App\Services\SettingService;
use App\Support\Role;
use Core\Exceptions\BookingException;
use Core\Exceptions\HttpException;
use Core\Exceptions\ValidationException;

final class App
{
    private static ?self $instance = null;

    private Router $router;

    private function __construct()
    {
        $this->router = new Router();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function boot(): self
    {
        date_default_timezone_set(config('app.timezone', 'America/Santiago'));
        setlocale(LC_TIME, 'es_CL.UTF-8', 'es_ES.UTF-8', 'es_CL', 'spanish');
        mb_internal_encoding('UTF-8');

        $this->configureErrors();

        Session::start();

        // Datos compartidos con todas las vistas.
        View::share('app', [
            'name'     => config('app.name'),
            'url'      => config('app.url'),
            'version'  => config('version.version'),
            'env'      => config('app.env'),
        ]);
        // Durante la instalación todavía no hay base de datos: el arranque no
        // puede depender de ella.
        try {
            View::share('authUser', Auth::user());
            View::share('authRole', Auth::role());
        } catch (\Throwable $e) {
            View::share('authUser', null);
            View::share('authRole', null);
        }

        // El instalador se antepone a todo: mientras falte, ninguna ruta
        // responde; una vez instalado, el asistente deja de existir.
        $this->router->globalMiddleware(['install']);

        require ROUTES_PATH . '/install.php';
        require ROUTES_PATH . '/web.php';
        require ROUTES_PATH . '/api.php';

        return $this;
    }

    private function configureErrors(): void
    {
        $debug = (bool) config('app.debug', false);

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            // Las obsolescencias avisan de un cambio futuro, no de un fallo
            // presente: se registran para atenderlas, pero jamás deben tumbar
            // una página en producción tras actualizar la versión de PHP.
            if (in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                logger()->warning('Función obsoleta en uso', [
                    'message' => $message,
                    'file'    => $file,
                    'line'    => $line,
                ]);

                return true;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                logger()->emergency('Error fatal', [
                    'message' => $error['message'],
                    'file'    => $error['file'],
                    'line'    => $error['line'],
                ]);
            }
        });
    }

    public function run(): void
    {
        $request = Request::capture();

        try {
            $response = $this->router->dispatch($request);
        } catch (\Throwable $e) {
            $response = $this->handleException($e, $request);
        }

        $response->send();
    }

    /** Convierte cualquier excepción en una respuesta segura para el usuario. */
    public function handleException(\Throwable $e, Request $request): Response
    {
        // 1) Errores de validación
        if ($e instanceof ValidationException) {
            if ($request->expectsJson()) {
                return Response::error($e->getMessage(), $e->errors, 422);
            }

            Session::flashInput($request->all(), $e->errors);
            Session::flash('error', $e->first() ?? $e->getMessage());

            return Response::back();
        }

        // 2) Reglas de negocio del motor de reservas
        if ($e instanceof BookingException) {
            if ($request->expectsJson()) {
                return Response::error($e->getMessage(), $e->errors, 409);
            }

            Session::flash('error', $e->getMessage());

            return Response::back();
        }

        // 3) Errores HTTP controlados
        if ($e instanceof HttpException) {
            if ($e->status >= 500) {
                logger()->error($e->getMessage(), ['uri' => $request->path()]);
            }

            return $this->errorResponse($e->status, $e->getMessage(), $request);
        }

        // 4) Cualquier otro error: se registra completo, se muestra genérico.
        logger()->error($e->getMessage(), [
            'exception' => $e::class,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'uri'       => $request->path(),
            'trace'     => config('app.debug') ? $e->getTraceAsString() : '(oculto)',
        ]);

        // Mientras el sistema no esté instalado, el error se muestra completo:
        // quien lo ve es quien está instalando, todavía no hay datos de nadie
        // que proteger, y sin el detalle es imposible avanzar en un hosting
        // compartido donde leer los logs es incómodo.
        if (config('app.debug') || !self::isInstalled()) {
            return Response::make($this->debugPage($e), 500);
        }

        return $this->errorResponse(500, 'Ocurrió un error inesperado. Ya fuimos notificados.', $request);
    }

    private function errorResponse(int $status, string $message, Request $request): Response
    {
        if ($request->expectsJson()) {
            return Response::error($message, [], $status);
        }

        $view = View::exists('errors.' . $status) ? 'errors.' . $status : 'errors.500';

        try {
            return Response::make(View::render($view, ['message' => $message, 'status' => $status]), $status);
        } catch (\Throwable) {
            return Response::make('<h1>' . $status . '</h1><p>' . e($message) . '</p>', $status);
        }
    }

    /**
     * Página técnica del error. Se muestra en desarrollo y durante la
     * instalación; nunca en un sistema instalado y en producción.
     */
    private function debugPage(\Throwable $e): string
    {
        $instalando = !self::isInstalled();

        $pistas = $instalando ? $this->hintsFor($e) : [];
        $lista  = '';

        foreach ($pistas as $pista) {
            $lista .= '<li style="margin-bottom:8px">' . $pista . '</li>';
        }

        $bloquePistas = $lista === '' ? '' :
            '<div style="background:#1B1408;border-left:3px solid #FFC400;padding:16px 18px;border-radius:6px;margin:20px 0">'
            . '<strong style="color:#FFC400;display:block;margin-bottom:10px">Qué revisar</strong>'
            . '<ul style="margin:0;padding-left:18px;color:#D8D4CB">' . $lista . '</ul></div>';

        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Error · Flava Studio</title></head>'
            . '<body style="margin:0;background:#0D0D0D;color:#FFFDF5;padding:30px 18px;'
            . 'font:14px/1.65 -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
            . '<div style="max-width:760px;margin:0 auto">'
            . ($instalando
                ? '<div style="font-size:.72rem;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:#FFC400;margin-bottom:8px">Instalación en curso</div>'
                : '')
            . '<h1 style="font-size:1.5rem;font-weight:800;margin:0 0 6px">Se produjo un error</h1>'
            . '<p style="color:rgba(255,253,245,.6);margin:0 0 22px">'
            . ($instalando
                ? 'Este detalle sólo se muestra porque el sistema todavía no está instalado.'
                : 'Detalle visible porque APP_DEBUG está activo.')
            . '</p>'
            . $bloquePistas
            . '<div style="background:#181818;border-radius:6px;padding:18px;margin-bottom:14px">'
            . '<div style="color:#FF8787;font-weight:700;margin-bottom:6px">' . e($e::class) . '</div>'
            . '<div style="font-size:1.02rem;margin-bottom:12px">' . e($e->getMessage()) . '</div>'
            . '<div style="color:#FFC400;font-family:ui-monospace,Menlo,monospace;font-size:.8rem">'
            . e(str_replace(BASE_PATH, '', $e->getFile())) . ':' . $e->getLine() . '</div>'
            . '</div>'
            . '<details style="background:#181818;border-radius:6px;padding:14px 18px">'
            . '<summary style="cursor:pointer;color:rgba(255,253,245,.65);font-size:.85rem">Traza completa</summary>'
            . '<pre style="white-space:pre-wrap;color:#8F8B82;font-size:.74rem;margin:12px 0 0">'
            . e(str_replace(BASE_PATH, '', $e->getTraceAsString())) . '</pre></details>'
            . '<p style="color:rgba(255,253,245,.32);font-size:.76rem;margin-top:22px">'
            . 'PHP ' . PHP_VERSION . ' · Flava Studio v' . e((string) config('version.version'))
            . ' · el detalle también queda en storage/logs</p>'
            . '</div></body></html>';
    }

    /**
     * Traduce los fallos típicos de un despliegue nuevo a instrucciones
     * concretas. Es la diferencia entre «algo falló» y saber qué tocar.
     *
     * @return array<int,string>
     */
    private function hintsFor(\Throwable $e): array
    {
        $mensaje = $e->getMessage();
        $pistas  = [];

        if (str_contains($mensaje, 'base de datos') || str_contains($mensaje, 'SQLSTATE') || $e instanceof \PDOException) {
            $pistas[] = 'Aún no hay conexión a la base de datos. Es lo normal antes de completar el asistente: entra a <code style="color:#FFC400">/instalar</code>.';
            $pistas[] = 'Si ya lo completaste, revisa <code style="color:#FFC400">config/database.php</code>: nombre de la base, usuario y que ese usuario esté asignado a la base en tu panel.';
        }

        if (str_contains($mensaje, 'session') || str_contains($mensaje, 'sesion') || str_contains($mensaje, 'sesión')) {
            $pistas[] = 'El servidor no puede iniciar sesiones. Suele ser permiso de escritura: revisa que <code style="color:#FFC400">storage/</code> tenga permisos 755.';
        }

        if (str_contains($mensaje, 'Permission denied') || str_contains($mensaje, 'failed to open stream')) {
            $pistas[] = 'Falta permiso de escritura. Dale 755 a <code style="color:#FFC400">storage</code>, <code style="color:#FFC400">config</code> y <code style="color:#FFC400">public/uploads</code> desde el Administrador de archivos, marcando «aplicar a subcarpetas».';
        }

        if (str_contains($mensaje, 'Vista no encontrada') || str_contains($mensaje, 'no encontrado')) {
            $pistas[] = 'Falta un archivo del proyecto: la subida pudo quedar incompleta. Vuelve a subir la carpeta que menciona el error.';
        }

        if ($pistas === []) {
            $pistas[] = 'Si acabas de subir el proyecto, comprueba que estén todas las carpetas (<code style="color:#FFC400">app</code>, <code style="color:#FFC400">core</code>, <code style="color:#FFC400">config</code>, <code style="color:#FFC400">public</code>, <code style="color:#FFC400">routes</code>, <code style="color:#FFC400">storage</code>).';
            $pistas[] = 'Verifica que <code style="color:#FFC400">storage/</code> tenga permisos de escritura (755).';
        }

        return $pistas;
    }

    /** ¿Existe el archivo que marca la instalación como terminada? */
    private static function isInstalled(): bool
    {
        return is_file(CONFIG_PATH . '/installed.php');
    }

    /** ¿El sistema está en modo mantención? (SUPER_ADMIN mantiene acceso) */
    public static function isDown(): bool
    {
        return is_file((string) config('app.maintenance_file'));
    }
}
