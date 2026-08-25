<?php
/**
 * Ruta: /core/bootstrap.php
 * Punto de arranque compartido por el front controller y la CLI.
 */

declare(strict_types=1);

define('BASE_PATH',    dirname(__DIR__));
define('APP_PATH',     BASE_PATH . '/app');
define('CORE_PATH',    BASE_PATH . '/core');
define('CONFIG_PATH',  BASE_PATH . '/config');
define('PUBLIC_PATH',  BASE_PATH . '/public');
define('ROUTES_PATH',  BASE_PATH . '/routes');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('DATABASE_PATH', BASE_PATH . '/database');
define('FLAVA_START',  microtime(true));

require CORE_PATH . '/helpers.php';
require CORE_PATH . '/Env.php';
require CORE_PATH . '/Autoloader.php';

Core\Env::load(BASE_PATH);

Core\Autoloader::register([
    'App\\'  => APP_PATH,
    'Core\\' => CORE_PATH,
]);

return Core\App::instance();
