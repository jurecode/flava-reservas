<?php
/**
 * Ruta: /core/Logger.php
 * Log a archivo en /storage/logs. Nunca expone datos sensibles al usuario final.
 */

namespace Core;

final class Logger
{
    private static ?self $instance = null;

    /** Claves que jamás deben quedar escritas en un log. */
    private const SENSITIVE = [
        'password', 'password_confirmation', 'token', '_token', 'github_token',
        'authorization', 'secret', 'api_key', 'access_token', 'rut',
    ];

    private function __construct(private readonly string $path)
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(STORAGE_PATH . '/logs');
    }

    public function emergency(string $message, array $context = []): void { $this->write('EMERGENCY', $message, $context); }
    public function error(string $message, array $context = []): void     { $this->write('ERROR', $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->write('WARNING', $message, $context); }
    public function info(string $message, array $context = []): void      { $this->write('INFO', $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->write('DEBUG', $message, $context); }

    /** Log dedicado del módulo de despliegue (/storage/logs/deploy.log). */
    public function deploy(string $message, array $context = []): void
    {
        $this->write('DEPLOY', $message, $context, 'deploy.log');
    }

    private function write(string $level, string $message, array $context, ?string $file = null): void
    {
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }

        $file ??= 'flava-' . date('Y-m-d') . '.log';
        $line  = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($this->sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            PHP_EOL
        );

        @file_put_contents($this->path . '/' . $file, $line, FILE_APPEND | LOCK_EX);
    }

    /** Enmascara secretos antes de escribirlos (obligatorio, spec §143). */
    public function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = $this->sanitize($value);
                continue;
            }

            if (in_array(strtolower((string) $key), self::SENSITIVE, true)) {
                $context[$key] = '***';
                continue;
            }

            if (is_string($value)) {
                $context[$key] = preg_replace(
                    ['/(github_pat_|ghp_|gho_|ghs_)[A-Za-z0-9_]+/', '/Bearer\s+[A-Za-z0-9._\-]+/i'],
                    ['$1***', 'Bearer ***'],
                    $value
                );
            }
        }

        return $context;
    }
}
