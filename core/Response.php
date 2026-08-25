<?php
/**
 * Ruta: /core/Response.php
 * Respuestas HTTP. El formato JSON es único en todo el sistema (spec §62).
 */

namespace Core;

final class Response
{
    private array $headers = [];

    public function __construct(
        private string $content = '',
        private int $status = 200,
        array $headers = []
    ) {
        $this->headers = $headers;
    }

    public static function make(string $content = '', int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /** Respuesta JSON exitosa estándar. */
    public static function success(string $message = '', array|object $data = [], int $status = 200): self
    {
        return self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    /** Respuesta JSON de error estándar. */
    public static function error(string $message, array $errors = [], int $status = 422): self
    {
        return self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function json(array|object $payload, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            $headers + ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $location = str_starts_with($to, 'http') ? $to : url($to);

        return new self('', $status, ['Location' => $location]);
    }

    public static function back(string $fallback = '/'): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        $host    = parse_url(config('app.url'), PHP_URL_HOST);

        // Sólo redirigir a referers del propio dominio.
        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            return self::redirect($referer);
        }

        return self::redirect($fallback);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            // Cabeceras de seguridad base.
            $defaults = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options'        => 'SAMEORIGIN',
                'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            ];

            foreach ($defaults + $this->headers as $key => $value) {
                header($key . ': ' . $value, true);
            }
        }

        echo $this->content;
    }
}
