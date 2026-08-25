<?php
/**
 * Ruta: /public/index.php
 * FRONT CONTROLLER ÚNICO de Flava Studio — https://flava.cl
 * Toda solicitud entra por aquí y la resuelve el Router.
 */

declare(strict_types=1);

// El dominio apunta a esta carpeta: los assets se sirven desde la raíz de la URL.
define('FLAVA_ENTRY', 'public');

/** @var Core\App $app */
$app = require dirname(__DIR__) . '/core/bootstrap.php';

$app->boot()->run();
