<?php
/**
 * Ruta: /config/app.php
 * Configuración central de la aplicación Flava Studio.
 * Los valores editables desde el panel viven en la tabla `settings`
 * (ver App\Services\SettingService). Aquí sólo lo estructural.
 */

return [
    // Identidad oficial. Nunca escribir el nombre a mano en las vistas.
    'name'          => env('APP_NAME', 'Flava Studio'),
    'legal_name'    => env('APP_LEGAL_NAME', 'Flava Studio SpA'),
    // Si APP_URL no está definida (instalación recién subida), se deduce del
    // dominio por el que llega la petición: así el instalador funciona en
    // cualquier hosting sin configurar nada primero.
    'url'           => rtrim(env('APP_URL', null) ?? detect_base_url(), '/'),
    'env'           => env('APP_ENV', 'production'),   // local | staging | production
    'debug'         => (bool) env('APP_DEBUG', false),
    'timezone'      => env('APP_TIMEZONE', 'America/Santiago'),
    'locale'        => env('APP_LOCALE', 'es_CL'),
    'currency'      => env('APP_CURRENCY', 'CLP'),
    'currency_symbol' => '$',
    'currency_decimals' => 0,

    // Clave usada por Crypto (cifrado de secretos). Debe vivir FUERA del webroot.
    'key'           => env('APP_KEY', null),

    // Sesión
    'session' => [
        'name'      => 'flava_session',
        'lifetime'  => 60 * 60 * 8,     // 8 horas para personal interno
        'secure'    => (bool) env('SESSION_SECURE', true),
        'same_site' => 'Lax',
    ],

    // Reglas de negocio por defecto (se pueden sobrescribir en `settings`)
    'booking' => [
        'slot_interval'         => 15,   // minutos entre inicios de slot
        'min_advance_minutes'   => 60,   // anticipación mínima para reservar online
        'max_advance_days'      => 60,   // hasta cuándo se puede reservar
        'buffer_minutes'        => 0,    // colchón entre citas
        'cancel_limit_hours'    => 2,    // no cancelar con menos de X horas
        'reschedule_limit_hours' => 2,
        'code_prefix'           => 'FLV',
        'reminder_hours_1'      => 24,
        'reminder_hours_2'      => 2,
    ],

    // Marca / diseño
    'brand' => [
        'yellow'     => '#FFC400',
        'honey'      => '#E9A400',
        'black'      => '#181818',
        'deep_black' => '#0D0D0D',
        'white'      => '#FFFDF5',
        'gray'       => '#F4F4F4',
    ],

    'maintenance_file' => STORAGE_PATH . '/framework/maintenance.flag',

    'uploads' => [
        'path'      => PUBLIC_PATH . '/uploads',
        'max_size'  => 4 * 1024 * 1024,
        'mimes'     => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
