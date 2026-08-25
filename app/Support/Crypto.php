<?php
/**
 * Ruta: /app/Support/Crypto.php
 * Cifrado simétrico para secretos almacenados (token de GitHub, spec §110).
 * Prefiere libsodium; si no está disponible usa OpenSSL AES-256-GCM.
 * La clave (APP_KEY) vive FUERA del webroot: /.env o /config/secrets.php.
 */

namespace App\Support;

final class Crypto
{
    private const PREFIX_SODIUM = 'sod$';
    private const PREFIX_GCM    = 'gcm$';

    public static function isConfigured(): bool
    {
        return self::key() !== null;
    }

    /** Clave binaria de 32 bytes derivada de APP_KEY. */
    private static function key(): ?string
    {
        $raw = (string) (config('app.key') ?? env('APP_KEY', ''));

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);

            return ($decoded !== false && strlen($decoded) === 32) ? $decoded : null;
        }

        // Compatibilidad: cualquier cadena se deriva a 32 bytes.
        return hash('sha256', $raw, true);
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /** Cifra un valor. Devuelve una cadena portable lista para guardar. */
    public static function encrypt(string $plaintext): string
    {
        $key = self::key();

        if ($key === null) {
            throw new \RuntimeException('APP_KEY no está configurada: no es posible cifrar secretos.');
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

            return self::PREFIX_SODIUM . base64_encode($nonce . $cipher);
        }

        $iv     = random_bytes(12);
        $tag    = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new \RuntimeException('No fue posible cifrar el valor.');
        }

        return self::PREFIX_GCM . base64_encode($iv . $tag . $cipher);
    }

    /** Descifra; devuelve null si el valor fue manipulado o la clave cambió. */
    public static function decrypt(?string $payload): ?string
    {
        $key = self::key();

        if ($key === null || $payload === null || $payload === '') {
            return null;
        }

        try {
            if (str_starts_with($payload, self::PREFIX_SODIUM)) {
                $raw = base64_decode(substr($payload, 4), true);

                if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                    return null;
                }

                $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $plain  = sodium_crypto_secretbox_open($cipher, $nonce, $key);

                return $plain === false ? null : $plain;
            }

            if (str_starts_with($payload, self::PREFIX_GCM)) {
                $raw = base64_decode(substr($payload, 4), true);

                if ($raw === false || strlen($raw) <= 28) {
                    return null;
                }

                $iv     = substr($raw, 0, 12);
                $tag    = substr($raw, 12, 16);
                $cipher = substr($raw, 28);
                $plain  = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

                return $plain === false ? null : $plain;
            }
        } catch (\Throwable $e) {
            logger()->error('Fallo al descifrar un secreto', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /** Muestra sólo la cola de un secreto: "github_pat_****F82K". */
    public static function mask(?string $secret, int $visible = 4): string
    {
        if ($secret === null || $secret === '') {
            return '';
        }

        $prefix = '';
        foreach (['github_pat_', 'ghp_', 'gho_', 'ghs_'] as $known) {
            if (str_starts_with($secret, $known)) {
                $prefix = $known;
                break;
            }
        }

        return $prefix . '****' . substr($secret, -$visible);
    }
}
