<?php
/**
 * Ruta: /app/Controllers/SuperAdmin/SystemController.php
 *
 * Funciones técnicas exclusivas del SUPER_ADMIN (spec §101):
 * estado del sistema, migraciones, respaldos, logs, mantención y cache.
 */

namespace App\Controllers\SuperAdmin;

use App\Models\Deployment;
use App\Services\ActivityLogger;
use App\Services\System\BackupService;
use App\Services\System\DeploymentService;
use App\Services\System\MaintenanceService;
use App\Services\System\MigrationService;
use Core\App;
use Core\Controller;
use Core\Response;
use Core\Session;

class SystemController extends Controller
{
    public function index(): Response
    {
        $deployment = new DeploymentService();

        return $this->view('superadmin.index', [
            'title'       => 'Sistema',
            'active'      => 'system',
            'status'      => $deployment->systemStatus(),
            'updates'     => null, // se consulta bajo demanda para no llamar a GitHub en cada carga
            'deployments' => (new Deployment())->history(5),
            'changelog'   => $deployment->changelog(3),
        ]);
    }

    public function info(): Response
    {
        return $this->view('superadmin.system.info', [
            'title'   => 'Información del servidor',
            'active'  => 'system',
            'status'  => (new DeploymentService())->systemStatus(),
            'php'     => $this->phpInfo(),
        ]);
    }

    // -----------------------------------------------------------------
    //  Migraciones
    // -----------------------------------------------------------------

    public function migrations(): Response
    {
        $service = new MigrationService();

        return $this->view('superadmin.system.migrations', [
            'title'     => 'Migraciones',
            'active'    => 'migrations',
            'pending'   => $service->pending(),
            'history'   => $service->history(40),
            'available' => $service->available(),
        ]);
    }

    public function runMigrations(): Response
    {
        $service = new MigrationService();
        $pending = $service->pending();

        if ($pending === []) {
            Session::flash('info', 'No hay migraciones pendientes.');

            return $this->back('/super-admin/migraciones');
        }

        // Respaldo obligatorio antes de tocar la estructura (spec §126).
        try {
            $backup = (new BackupService())->create('migrations');
            Session::flash('info', 'Respaldo creado: ' . $backup['name']);
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo crear el respaldo previo: ' . $e->getMessage() . '. Las migraciones no se ejecutaron.');

            return $this->back('/super-admin/migraciones');
        }

        $result = $service->run();

        if ($result['failed'] !== null) {
            ActivityLogger::log('migration.failed', 'system', null, 'Falló ' . $result['failed']);

            $message = sprintf(
                'Falló la migración %s en la sentencia %d: %s',
                $result['failed'],
                (int) $result['statement'],
                $result['error']
            );

            if ($result['partial']) {
                // El DDL de MySQL no es reversible: hay que avisarlo con claridad.
                $message .= ' Las sentencias anteriores de ese archivo YA se aplicaron y '
                    . 'no pueden revertirse automáticamente. Revisa la estructura o restaura el respaldo recién creado.';
            }

            if ($result['executed'] !== []) {
                $message .= ' Migraciones completadas antes del error: ' . implode(', ', $result['executed']) . '.';
            }

            Session::flash('error', $message);

            return $this->back('/super-admin/migraciones');
        }

        ActivityLogger::log('migration.executed', 'system', null, count($result['executed']) . ' migración(es) aplicada(s)');

        return $this->redirectWith(
            '/super-admin/migraciones',
            count($result['executed']) . ' migración(es) aplicada(s) correctamente.'
        );
    }

    // -----------------------------------------------------------------
    //  Respaldos
    // -----------------------------------------------------------------

    public function backups(): Response
    {
        $service = new BackupService();

        return $this->view('superadmin.system.backups', [
            'title'   => 'Respaldos',
            'active'  => 'backups',
            'backups' => $service->list(30),
            'path'    => $service->path(),
        ]);
    }

    public function createBackup(): Response
    {
        try {
            $backup = (new BackupService())->create((string) ($this->request->input('label') ?: 'manual'));
        } catch (\Throwable $e) {
            Session::flash('error', 'No se pudo crear el respaldo: ' . $e->getMessage());

            return $this->back('/super-admin/respaldos');
        }

        ActivityLogger::log('backup.created', 'system', null, 'Respaldo ' . $backup['name']);

        return $this->redirectWith(
            '/super-admin/respaldos',
            'Respaldo creado: ' . $backup['name'] . ' (' . BackupService::humanSize($backup['size']) . ')'
        );
    }

    public function pruneBackups(): Response
    {
        $keep    = max(3, $this->request->integer('keep', 10));
        $removed = (new BackupService())->prune($keep);

        ActivityLogger::log('backup.pruned', 'system', null, $removed . ' respaldo(s) eliminados');

        return $this->redirectWith('/super-admin/respaldos', $removed . ' respaldo(s) antiguos eliminados.');
    }

    // -----------------------------------------------------------------
    //  Logs y rutas
    // -----------------------------------------------------------------

    public function logs(): Response
    {
        $directory = STORAGE_PATH . '/logs';
        $files     = array_map('basename', glob($directory . '/*.log') ?: []);
        rsort($files);

        $selected = (string) ($this->request->input('file') ?: ($files[0] ?? ''));
        $content  = '';

        // Sólo se abren archivos del propio directorio de logs.
        if ($selected !== '' && in_array($selected, $files, true)) {
            $content = $this->tail($directory . '/' . $selected, 400);
        }

        return $this->view('superadmin.system.logs', [
            'title'    => 'Logs técnicos',
            'active'   => 'logs',
            'files'    => $files,
            'selected' => $selected,
            'content'  => $content,
        ]);
    }

    public function routes(): Response
    {
        return $this->view('superadmin.system.routes', [
            'title'  => 'Rutas registradas',
            'active' => 'routes',
            'routes' => App::instance()->router()->routes(),
        ]);
    }

    // -----------------------------------------------------------------
    //  Mantención y cache
    // -----------------------------------------------------------------

    public function toggleMaintenance(): Response
    {
        $service = new MaintenanceService();

        if ($service->isEnabled()) {
            $service->disable();
            Session::flash('success', 'Modo mantención desactivado. El sitio vuelve a estar público.');
        } else {
            $ok = $service->enable((string) ($this->request->input('message') ?: '') ?: null);
            Session::flash(
                $ok ? 'success' : 'error',
                $ok
                    ? 'Modo mantención activado. Sólo tú puedes navegar el sistema.'
                    : 'No se pudo activar la mantención: revisa los permisos de /storage/framework.'
            );
        }

        return $this->back('/super-admin');
    }

    public function clearCache(): Response
    {
        (new DeploymentService())->clearCache();
        ActivityLogger::log('cache.cleared', 'system', null, 'Cache limpiada');

        return $this->redirectWith('/super-admin', 'Cache limpiada.');
    }

    // -----------------------------------------------------------------
    //  Internos
    // -----------------------------------------------------------------

    /** Datos del entorno. Nunca se expone phpinfo() completo (spec §142). */
    private function phpInfo(): array
    {
        return [
            'version'          => PHP_VERSION,
            'sapi'             => PHP_SAPI,
            'memory_limit'     => ini_get('memory_limit'),
            'max_execution'    => ini_get('max_execution_time'),
            'upload_max'       => ini_get('upload_max_filesize'),
            'post_max'         => ini_get('post_max_size'),
            'timezone'         => date_default_timezone_get(),
            'extensions'       => [
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'curl'      => extension_loaded('curl'),
                'mbstring'  => extension_loaded('mbstring'),
                'openssl'   => extension_loaded('openssl'),
                'sodium'    => extension_loaded('sodium'),
                'zip'       => extension_loaded('zip'),
                'gd'        => extension_loaded('gd'),
            ],
            'disable_functions' => (string) ini_get('disable_functions'),
        ];
    }

    /** Últimas N líneas de un archivo sin cargarlo completo en memoria. */
    private function tail(string $file, int $lines): string
    {
        if (!is_file($file)) {
            return '';
        }

        $handle = @fopen($file, 'rb');

        if ($handle === false) {
            return '';
        }

        $buffer   = '';
        $chunk    = 4096;
        $position = -1;
        $found    = 0;
        $size     = filesize($file) ?: 0;

        while ($found < $lines && abs($position) < $size) {
            $seek   = max(-$size, $position - $chunk);
            $length = min($chunk, $size + $position + 1);

            fseek($handle, $seek, SEEK_END);
            $data     = (string) fread($handle, $length);
            $buffer   = $data . $buffer;
            $found    = substr_count($buffer, "\n");
            $position = $seek;
        }

        fclose($handle);

        $all = explode("\n", $buffer);

        return implode("\n", array_slice($all, -$lines));
    }
}
