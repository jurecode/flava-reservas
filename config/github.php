<?php
/**
 * Ruta: /config/github.php
 * Configuración por defecto de la integración GitHub.
 * Los valores reales se administran desde el panel SUPER_ADMIN y se guardan
 * en `settings`. El TOKEN nunca se guarda aquí en texto plano: llega cifrado
 * (App\Support\Crypto) o desde variable de entorno GITHUB_TOKEN.
 */

return [
    'enabled'     => (bool) env('GITHUB_ENABLED', false),
    'owner'       => env('GITHUB_OWNER', ''),
    'repository'  => env('GITHUB_REPOSITORY', ''),
    'branch'      => env('GITHUB_BRANCH', 'main'),
    'token'       => env('GITHUB_TOKEN', null), // preferente: variable de entorno
    'repo_path'   => env('GITHUB_REPO_PATH', BASE_PATH), // raíz del working copy
    'api_base'    => 'https://api.github.com',
    'timeout'     => 15,

    // Comportamiento del despliegue
    'auto_backup'          => true,
    'maintenance_on_deploy' => true,
    'strategy'             => env('DEPLOY_STRATEGY', 'auto'), // auto | git | zip
];
