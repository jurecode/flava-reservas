<?php
/**
 * Ruta: /config/secrets.example.php
 * Copiar como /config/secrets.php (NUNCA versionar el real).
 * Alternativa para hostings que no permiten variables de entorno.
 *
 * Generar APP_KEY con:  php bin/flava key:generate
 */

return [
    'APP_KEY'       => '',   // base64:xxxxx  (clave de cifrado AES-256-GCM / sodium)
    'DB_PASSWORD'   => '',
    'GITHUB_TOKEN'  => '',   // opcional; preferible administrarlo desde el panel
];
