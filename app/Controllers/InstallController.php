<?php
/**
 * Ruta: /app/Controllers/InstallController.php
 *
 * Asistente de instalación. Sólo responde mientras el sistema NO está
 * instalado: en cuanto existe /config/installed.php, InstallMiddleware corta
 * el acceso a todas estas rutas.
 */

namespace App\Controllers;

use App\Services\System\InstallerService;
use App\Support\Crypto;
use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Session;
use Core\Validator;

class InstallController extends Controller
{
    private const SESSION_DB = '_install_db';

    private InstallerService $installer;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->installer = new InstallerService();
    }

    /** Punto de entrada: siempre lleva al primer paso pendiente. */
    public function index(): Response
    {
        return $this->redirect('/instalar/requisitos');
    }

    // =================================================================
    //  PASO 1 · Requisitos
    // =================================================================
    public function requirements(): Response
    {
        $result = $this->installer->requirements();

        return $this->view('install.requirements', [
            'title'  => 'Requisitos del servidor',
            'step'   => 1,
            'result' => $result,
        ]);
    }

    // =================================================================
    //  PASO 2 · Base de datos
    // =================================================================
    public function database(): Response
    {
        return $this->view('install.database', [
            'title'   => 'Conexión a la base de datos',
            'step'    => 2,
            'config'  => Session::get(self::SESSION_DB, [
                'host' => 'localhost', 'port' => '3306',
                'database' => '', 'username' => '', 'password' => '',
            ]),
            'test'    => Session::getFlash('_install_test'),
            'manual'  => Session::getFlash('_install_manual'),
        ]);
    }

    public function testDatabase(): Response
    {
        $this->verifyCsrf();

        $validator = new Validator($this->request->all(), [
            'host'     => 'required|max:120',
            'port'     => 'required|integer',
            'database' => 'required|max:120',
            'username' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors(), 'Completa los datos de conexión.');
        }

        $config = [
            'host'     => (string) $this->request->input('host'),
            'port'     => (string) $this->request->input('port'),
            'database' => (string) $this->request->input('database'),
            'username' => (string) $this->request->input('username'),
            'password' => (string) $this->request->raw('password', ''),
        ];

        $test = $this->installer->testConnection($config);

        Session::put(self::SESSION_DB, $config);
        Session::flash('_install_test', $test);

        if (!$test['ok']) {
            Session::flash('error', $test['message']);

            return $this->redirect('/instalar/base-de-datos');
        }

        // Conexión buena: intentamos dejar el archivo de configuración escrito.
        if (!$this->installer->writeDatabaseConfig($config)) {
            Session::flash('_install_manual', $this->installer->databaseConfigContents($config));
            Session::flash(
                'warning',
                'La conexión funciona, pero /config no permite escritura. Crea el archivo manualmente con el contenido de abajo.'
            );

            return $this->redirect('/instalar/base-de-datos');
        }

        $this->installer->ensureAppKey();

        Session::flash('success', $test['message'] . ' Configuración guardada.');

        return $this->redirect('/instalar/base-de-datos/confirmar');
    }

    /** Re-verifica tras una escritura manual del archivo. */
    public function confirmDatabase(): Response
    {
        $config = Session::get(self::SESSION_DB);

        if (!is_array($config)) {
            return $this->redirect('/instalar/base-de-datos');
        }

        $test = $this->installer->testConnection($config);

        if (!$test['ok']) {
            Session::flash('error', $test['message']);

            return $this->redirect('/instalar/base-de-datos');
        }

        $this->installer->ensureAppKey();

        return $this->redirect('/instalar/base-de-datos/confirmar' === $this->request->path()
            ? '/instalar/esquema'
            : '/instalar/esquema');
    }

    // =================================================================
    //  PASO 3 · Esquema
    // =================================================================
    public function schema(): Response
    {
        return $this->view('install.schema', [
            'title'  => 'Estructura de la base de datos',
            'step'   => 3,
            'status' => $this->installer->schemaStatus(),
            'result' => Session::getFlash('_install_schema'),
        ]);
    }

    public function importSchema(): Response
    {
        $this->verifyCsrf();

        $result = $this->installer->importSchema();

        Session::flash('_install_schema', $result);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirect($result['ok'] ? '/instalar/administrador' : '/instalar/esquema');
    }

    // =================================================================
    //  PASO 4 · Administrador
    // =================================================================
    public function admin(): Response
    {
        if (!$this->installer->schemaStatus()['installed']) {
            Session::flash('error', 'Primero hay que crear las tablas.');

            return $this->redirect('/instalar/esquema');
        }

        return $this->view('install.admin', [
            'title' => 'Tu cuenta de administrador',
            'step'  => 4,
        ]);
    }

    public function storeAdmin(): Response
    {
        $this->verifyCsrf();

        $validator = new Validator($this->request->all(), [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'email'      => 'required|email|max:150',
            'phone'      => 'nullable|phone',
            'password'   => 'required|min:8|max:100|confirmed',
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min'       => 'Usa al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $password = (string) $this->request->raw('password', '');

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return $this->backWithErrors([
                'password' => ['Combina letras y números para que sea más segura.'],
            ]);
        }

        $data = $validator->validated();
        $data['password'] = $password;

        try {
            $this->installer->createAdmin($data);
        } catch (\Throwable $e) {
            logger()->error('Instalador: no se pudo crear el administrador', ['error' => $e->getMessage()]);
            Session::flash('error', 'No se pudo crear la cuenta: ' . $e->getMessage());

            return $this->back();
        }

        Session::flash('success', 'Cuenta creada. Ya casi terminamos.');

        return $this->redirect('/instalar/negocio');
    }

    // =================================================================
    //  PASO 5 · Datos del negocio
    // =================================================================
    public function business(): Response
    {
        if (!$this->installer->hasUsers()) {
            return $this->redirect('/instalar/administrador');
        }

        return $this->view('install.business', [
            'title'      => 'Datos de tu barbería',
            'step'       => 5,
            'suggestUrl' => $this->guessUrl(),
        ]);
    }

    public function storeBusiness(): Response
    {
        $this->verifyCsrf();

        $validator = new Validator($this->request->all(), [
            'name'     => 'required|min:2|max:120',
            'email'    => 'nullable|email|max:150',
            'phone'    => 'nullable|phone',
            'whatsapp' => 'nullable|phone',
            'address'  => 'nullable|max:255',
            'app_url'  => 'nullable|url|max:200',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $validator->validated();

        $this->installer->saveBusiness($data);

        if (!empty($data['app_url'])) {
            $this->installer->saveAppUrl((string) $data['app_url']);
        }

        return $this->redirect('/instalar/finalizar');
    }

    // =================================================================
    //  PASO 6 · Cierre
    // =================================================================
    public function finish(): Response
    {
        $verification = $this->installer->verify();

        return $this->view('install.finish', [
            'title'        => 'Instalación completada',
            'step'         => 6,
            'verification' => $verification,
            'locked'       => false,
        ]);
    }

    public function lock(): Response
    {
        $this->verifyCsrf();

        $verification = $this->installer->verify();

        if (!$verification['ok']) {
            Session::flash('error', 'Todavía hay problemas por resolver: ' . implode(' ', $verification['problems']));

            return $this->redirect('/instalar/finalizar');
        }

        if (!$this->installer->lock()) {
            Session::flash(
                'error',
                'No se pudo cerrar el instalador: /config no permite escritura. '
                . 'Crea manualmente el archivo /config/installed.php o dale permisos 755 a la carpeta.'
            );

            return $this->redirect('/instalar/finalizar');
        }

        Session::forget(self::SESSION_DB);
        Session::flash('success', '¡Listo! Flava Studio está instalado. Inicia sesión para empezar.');

        return $this->redirect('/login');
    }

    // -----------------------------------------------------------------
    //  Internos
    // -----------------------------------------------------------------

    /** Adivina la URL pública desde la petición actual. */
    private function guessUrl(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https://' : 'http://') . $host;
    }
}
