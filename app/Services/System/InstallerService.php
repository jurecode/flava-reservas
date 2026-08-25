<?php
/**
 * Ruta: /app/Services/System/InstallerService.php
 *
 * Asistente de instalación paso a paso.
 *
 * Pensado para hostings compartidos tipo Hostinger, donde la base de datos se
 * crea a mano desde el panel y no hay SSH: todo lo demás se resuelve desde el
 * navegador.
 *
 * SEGURIDAD: al terminar se escribe /config/installed.php. Mientras ese archivo
 * exista, el instalador queda cerrado y ninguna de sus rutas responde.
 */

namespace App\Services\System;

use App\Models\Setting;
use App\Models\User;
use App\Support\Crypto;
use App\Support\Role;
use App\Support\Str;
use Core\Database;
use PDO;

final class InstallerService
{
    private const LOCK = '/config/installed.php';

    /** Extensiones sin las cuales el sistema no funciona. */
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'json'];

    /** Extensiones que habilitan funciones concretas. */
    private const OPTIONAL_EXTENSIONS = [
        'curl'    => 'Integración con GitHub y actualizaciones',
        'gd'      => 'Redimensionado de las imágenes que subas',
        'openssl' => 'Cifrado de secretos (alternativa: sodium)',
        'sodium'  => 'Cifrado de secretos (preferido)',
        'zip'     => 'Respaldos comprimidos',
    ];

    // -----------------------------------------------------------------
    //  Estado
    // -----------------------------------------------------------------

    public function isInstalled(): bool
    {
        return is_file(BASE_PATH . self::LOCK);
    }

    public function installedAt(): ?string
    {
        if (!$this->isInstalled()) {
            return null;
        }

        $data = @include BASE_PATH . self::LOCK;

        return is_array($data) ? ($data['installed_at'] ?? null) : null;
    }

    // -----------------------------------------------------------------
    //  Paso 1 · Requisitos
    // -----------------------------------------------------------------

    /**
     * @return array{ok:bool,checks:array<int,array{label:string,ok:bool,detail:string,critical:bool}>}
     */
    public function requirements(): array
    {
        $checks = [];

        // PHP
        $phpOk    = version_compare(PHP_VERSION, '8.1.0', '>=');
        $checks[] = [
            'label'    => 'PHP 8.1 o superior',
            'ok'       => $phpOk,
            'detail'   => 'Tienes PHP ' . PHP_VERSION . ($phpOk ? '' : ' · cámbialo en hPanel → Avanzado → Configuración PHP'),
            'critical' => true,
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $loaded   = extension_loaded($extension);
            $checks[] = [
                'label'    => 'Extensión ' . $extension,
                'ok'       => $loaded,
                'detail'   => $loaded ? 'Disponible' : 'Actívala en hPanel → Avanzado → Configuración PHP → Extensiones',
                'critical' => true,
            ];
        }

        // Al menos un mecanismo de cifrado
        $crypto   = extension_loaded('sodium') || extension_loaded('openssl');
        $checks[] = [
            'label'    => 'Cifrado (sodium u openssl)',
            'ok'       => $crypto,
            'detail'   => $crypto
                ? (extension_loaded('sodium') ? 'sodium disponible' : 'openssl disponible')
                : 'Sin esto no se pueden guardar secretos cifrados',
            'critical' => true,
        ];

        // mod_rewrite: si esta página cargó con URL limpia, funciona
        $rewrite  = $this->rewriteWorks();
        $checks[] = [
            'label'    => 'URLs limpias (mod_rewrite)',
            'ok'       => $rewrite,
            'detail'   => $rewrite ? 'Funcionando' : 'Revisa que el .htaccess se haya subido',
            'critical' => true,
        ];

        // Carpetas escribibles
        foreach ($this->writablePaths() as $label => $path) {
            $writable = is_dir($path) ? is_writable($path) : @mkdir($path, 0775, true);
            $checks[] = [
                'label'    => 'Escritura en ' . $label,
                'ok'       => (bool) $writable,
                'detail'   => $writable ? 'Correcto' : 'Dale permisos 755 desde el Administrador de archivos',
                'critical' => true,
            ];
        }

        // Opcionales
        foreach (self::OPTIONAL_EXTENSIONS as $extension => $purpose) {
            if (in_array($extension, ['sodium', 'openssl'], true)) {
                continue; // ya se evaluaron juntas
            }

            $loaded   = extension_loaded($extension);
            $checks[] = [
                'label'    => 'Extensión ' . $extension . ' (opcional)',
                'ok'       => $loaded,
                'detail'   => $purpose . ($loaded ? '' : ' · el sistema funciona igual sin ella'),
                'critical' => false,
            ];
        }

        $blocking = array_filter($checks, static fn (array $c): bool => $c['critical'] && !$c['ok']);

        return ['ok' => $blocking === [], 'checks' => $checks];
    }

    /** @return array<string,string> */
    public function writablePaths(): array
    {
        return [
            '/config'          => CONFIG_PATH,
            '/storage/logs'    => STORAGE_PATH . '/logs',
            '/storage/cache'   => STORAGE_PATH . '/cache',
            '/storage/backups' => STORAGE_PATH . '/backups',
            '/storage/framework' => STORAGE_PATH . '/framework',
            '/public/uploads'  => PUBLIC_PATH . '/uploads',
        ];
    }

    private function rewriteWorks(): bool
    {
        // Si la petición llegó a /instalar sin index.php en la URL, hay rewrite.
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return !str_contains($uri, 'index.php');
    }

    // -----------------------------------------------------------------
    //  Paso 2 · Base de datos
    // -----------------------------------------------------------------

    /**
     * Prueba la conexión con las credenciales indicadas.
     *
     * @return array{ok:bool,message:string,tables:int,server:?string}
     */
    public function testConnection(array $config): array
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'] ?? 'localhost',
            $config['port'] ?? '3306',
            $config['database'] ?? ''
        );

        try {
            $pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
            ]);

            $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $server = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            return [
                'ok'      => true,
                'message' => 'Conexión correcta con ' . $config['database'] . '.',
                'tables'  => $tables,
                'server'  => $server,
            ];
        } catch (\PDOException $e) {
            return [
                'ok'      => false,
                'message' => $this->friendlyDbError($e),
                'tables'  => 0,
                'server'  => null,
            ];
        }
    }

    /** Traduce los errores de MySQL a algo accionable. */
    private function friendlyDbError(\PDOException $e): string
    {
        $raw = $e->getMessage();

        return match (true) {
            // MySQL responde «Access denied» tanto si la clave está mal como si
            // el usuario existe pero no tiene permisos sobre esa base. Lo
            // segundo es el tropiezo más habitual: en Hostinger hay que asignar
            // el usuario a la base después de crearlo.
            str_contains($raw, 'Access denied') => 'No se pudo entrar con esos datos. Revisa tres cosas: '
                . 'que el usuario y la contraseña sean correctos, que el nombre de la base esté bien escrito '
                . '(incluido el prefijo tipo u123456789_) y, sobre todo, que el usuario esté ASIGNADO a esa base '
                . 'en el panel de tu hosting.',

            str_contains($raw, 'Unknown database') => 'Esa base de datos no existe. Créala primero desde el panel '
                . 'de tu hosting (Bases de datos → MySQL) y copia el nombre exacto, con prefijo incluido.',

            str_contains($raw, 'Connection refused'),
            str_contains($raw, 'No such file')  => 'No se pudo conectar al servidor MySQL. En hosting compartido '
                . 'el host casi siempre es «localhost».',

            str_contains($raw, 'timed out')     => 'La conexión tardó demasiado. Verifica el host: en hosting '
                . 'compartido suele ser «localhost», no una dirección remota.',

            default => 'No se pudo conectar: ' . mb_substr($raw, 0, 160),
        };
    }

    /** Contenido de /config/database.php, para escribirlo o pegarlo a mano. */
    public function databaseConfigContents(array $config): string
    {
        $q = static fn (?string $value): string => var_export((string) $value, true);

        return "<?php\n"
            . "/**\n"
            . " * Ruta: /config/database.php\n"
            . " * Generado por el instalador el " . date('Y-m-d H:i') . ".\n"
            . " * Este archivo NO se versiona: contiene credenciales.\n"
            . " */\n\n"
            . "return [\n"
            . "    'driver'    => 'mysql',\n"
            . "    'host'      => " . $q($config['host'] ?? 'localhost') . ",\n"
            . "    'port'      => " . $q($config['port'] ?? '3306') . ",\n"
            . "    'database'  => " . $q($config['database'] ?? '') . ",\n"
            . "    'username'  => " . $q($config['username'] ?? '') . ",\n"
            . "    'password'  => " . $q($config['password'] ?? '') . ",\n"
            . "    'charset'   => 'utf8mb4',\n"
            . "    'collation' => 'utf8mb4_unicode_ci',\n"
            . "    'options'   => [],\n"
            . "];\n";
    }

    /** Escribe /config/database.php. Devuelve false si la carpeta no permite escritura. */
    public function writeDatabaseConfig(array $config): bool
    {
        $file = CONFIG_PATH . '/database.php';

        if (@file_put_contents($file, $this->databaseConfigContents($config), LOCK_EX) === false) {
            return false;
        }

        @chmod($file, 0640);

        return true;
    }

    /** Genera y guarda APP_KEY en /config/secrets.php, fuera del webroot. */
    public function ensureAppKey(): bool
    {
        if (Crypto::isConfigured()) {
            return true;
        }

        $key  = Crypto::generateKey();
        $file = CONFIG_PATH . '/secrets.php';

        $existing = is_file($file) ? (@include $file) : [];
        $existing = is_array($existing) ? $existing : [];
        $existing['APP_KEY'] = $key;

        $body = "<?php\n"
            . "/**\n"
            . " * Ruta: /config/secrets.php\n"
            . " * Secretos de la instalación. NUNCA subir a GitHub.\n"
            . " */\n\n"
            . "return " . var_export($existing, true) . ";\n";

        if (@file_put_contents($file, $body, LOCK_EX) === false) {
            return false;
        }

        @chmod($file, 0640);
        \Core\Env::set('APP_KEY', $key);

        return true;
    }

    // -----------------------------------------------------------------
    //  Paso 3 · Esquema
    // -----------------------------------------------------------------

    /**
     * @return array{installed:bool,tables:int,missing:array<int,string>}
     */
    public function schemaStatus(): array
    {
        $expected = [
            'branches', 'users', 'customers', 'customer_notes', 'service_categories',
            'services', 'barbers', 'barber_services', 'barber_schedules', 'blocked_times',
            'bookings', 'booking_status_history', 'payments', 'notifications',
            'product_categories', 'products', 'orders', 'order_items',
            'loyalty_transactions', 'coupons', 'settings', 'activity_logs',
            'migrations', 'deployments',
        ];

        try {
            $db      = Database::instance();
            $present = array_map(
                static fn (array $row): string => (string) reset($row),
                $db->select('SHOW TABLES')
            );
        } catch (\Throwable) {
            return ['installed' => false, 'tables' => 0, 'missing' => $expected];
        }

        $missing = array_values(array_diff($expected, $present));

        return [
            'installed' => $missing === [],
            'tables'    => count($present),
            'missing'   => $missing,
        ];
    }

    /**
     * Importa /database/flava.sql. Sólo se ejecuta si faltan tablas: nunca
     * sobrescribe una base que ya tiene datos.
     *
     * @return array{ok:bool,message:string,statements:int}
     */
    public function importSchema(): array
    {
        $status = $this->schemaStatus();

        if ($status['installed']) {
            return ['ok' => true, 'message' => 'El esquema ya estaba creado.', 'statements' => 0];
        }

        if ($status['tables'] > 0 && $status['missing'] !== []) {
            // Hay tablas pero incompletas: puede ser una instalación a medias.
            logger()->warning('Instalador: base parcialmente poblada', ['faltan' => count($status['missing'])]);
        }

        $file = DATABASE_PATH . '/flava.sql';

        if (!is_file($file)) {
            return ['ok' => false, 'message' => 'No se encontró /database/flava.sql en el servidor.', 'statements' => 0];
        }

        $sql = (string) @file_get_contents($file);

        if (trim($sql) === '') {
            return ['ok' => false, 'message' => 'El archivo flava.sql está vacío.', 'statements' => 0];
        }

        $statements = (new MigrationService())->splitStatements($sql);
        $db         = Database::instance();
        $executed   = 0;

        try {
            foreach ($statements as $statement) {
                $db->pdo()->exec($statement);
                $executed++;
            }
        } catch (\Throwable $e) {
            logger()->error('Instalador: fallo al importar el esquema', [
                'statement' => $executed,
                'error'     => $e->getMessage(),
            ]);

            return [
                'ok'         => false,
                'message'    => 'Error al crear la tabla ' . ($executed + 1) . ': ' . mb_substr($e->getMessage(), 0, 200),
                'statements' => $executed,
            ];
        }

        return [
            'ok'         => true,
            'message'    => 'Base de datos creada: ' . $this->schemaStatus()['tables'] . ' tablas.',
            'statements' => $executed,
        ];
    }

    // -----------------------------------------------------------------
    //  Paso 4 · Administrador
    // -----------------------------------------------------------------

    /** ¿Ya existe algún usuario interno? */
    public function hasUsers(): bool
    {
        try {
            return (int) Database::instance()->scalar('SELECT COUNT(*) FROM users') > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Crea (o reemplaza) el SUPER_ADMIN. El usuario semilla que trae flava.sql
     * se elimina: no debe quedar ninguna credencial conocida.
     */
    public function createAdmin(array $data): array
    {
        $users = new User();
        $db    = Database::instance();

        return $db->transaction(function () use ($users, $db, $data): array {
            // Fuera el admin de ejemplo de flava.sql
            $db->statement("DELETE FROM users WHERE email = 'admin@flava.cl' AND must_change_password = 1");

            $existing = $users->findByEmail((string) $data['email']);

            if ($existing !== null) {
                $users->update((int) $existing['id'], [
                    'first_name'           => $data['first_name'],
                    'last_name'            => $data['last_name'],
                    'role'                 => Role::SUPER_ADMIN,
                    'status'               => 1,
                    'must_change_password' => 0,
                ]);
                $users->updatePassword((int) $existing['id'], (string) $data['password']);

                return $users->find((int) $existing['id']) ?? [];
            }

            $id = $users->createUser([
                'branch_id'            => 1,
                'first_name'           => $data['first_name'],
                'last_name'            => $data['last_name'],
                'email'                => $data['email'],
                'phone'                => $data['phone'] ?? null,
                'password'             => $data['password'],
                'role'                 => Role::SUPER_ADMIN,
                'status'               => 1,
                'must_change_password' => 0,
            ]);

            return $users->find($id) ?? [];
        });
    }

    // -----------------------------------------------------------------
    //  Paso 5 · Datos del negocio
    // -----------------------------------------------------------------

    public function saveBusiness(array $data): void
    {
        $settings = new Setting();
        $phone    = Str::phone($data['phone'] ?? null);

        $map = [
            'business_name'     => $data['name'] ?? 'Flava Studio',
            'business_email'    => $data['email'] ?? '',
            'business_phone'    => $phone ?? '',
            'business_whatsapp' => Str::phone($data['whatsapp'] ?? null) ?? $phone ?? '',
            'business_address'  => $data['address'] ?? '',
        ];

        foreach ($map as $key => $value) {
            $settings->put($key, $value, null, null, 'general');
        }

        // La sucursal principal refleja los mismos datos
        Database::instance()->update('branches', [
            'name'     => $map['business_name'],
            'address'  => $map['business_address'],
            'phone'    => $map['business_phone'],
            'whatsapp' => $map['business_whatsapp'],
            'email'    => $map['business_email'],
        ], 'is_default = 1');

        \App\Services\SettingService::flush();
    }

    /** Guarda la URL pública en /config/secrets.php como APP_URL. */
    public function saveAppUrl(string $url): void
    {
        $url  = rtrim(trim($url), '/');
        $file = CONFIG_PATH . '/secrets.php';

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $existing = is_file($file) ? (@include $file) : [];
        $existing = is_array($existing) ? $existing : [];
        $existing['APP_URL'] = $url;
        $existing['APP_ENV'] = 'production';
        $existing['APP_DEBUG'] = 'false';

        @file_put_contents(
            $file,
            "<?php\n\nreturn " . var_export($existing, true) . ";\n",
            LOCK_EX
        );
        @chmod($file, 0640);
    }

    // -----------------------------------------------------------------
    //  Paso 6 · Cerrar el instalador
    // -----------------------------------------------------------------

    public function lock(): bool
    {
        $body = "<?php\n"
            . "/**\n"
            . " * Ruta: /config/installed.php\n"
            . " * Marca la instalación como completada. Mientras este archivo\n"
            . " * exista, el asistente /instalar queda cerrado.\n"
            . " *\n"
            . " * Para reinstalar desde cero: elimina este archivo.\n"
            . " */\n\n"
            . "return " . var_export([
                'installed_at' => date('Y-m-d H:i:s'),
                'version'      => config('version.version'),
                'php'          => PHP_VERSION,
            ], true) . ";\n";

        if (@file_put_contents(BASE_PATH . self::LOCK, $body, LOCK_EX) === false) {
            return false;
        }

        @chmod(BASE_PATH . self::LOCK, 0640);

        return true;
    }

    /** Comprobaciones finales antes de dar por buena la instalación. */
    public function verify(): array
    {
        $problems = [];

        if (!Database::instance()->isConnected()) {
            $problems[] = 'No hay conexión con la base de datos.';
        }

        if (!$this->schemaStatus()['installed']) {
            $problems[] = 'Faltan tablas en la base de datos.';
        }

        if (!$this->hasUsers()) {
            $problems[] = 'No se creó ningún usuario administrador.';
        }

        if (!Crypto::isConfigured()) {
            $problems[] = 'Falta APP_KEY: los secretos no se podrán cifrar.';
        }

        return ['ok' => $problems === [], 'problems' => $problems];
    }
}
