<?php
/**
 * Ruta: /core/Request.php
 * Encapsula la solicitud HTTP entrante.
 */

namespace Core;

final class Request
{
    private static ?self $current = null;

    private array $routeParams = [];

    private function __construct(
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files,
        private readonly array $cookies,
    ) {
    }

    public static function capture(): self
    {
        $body = $_POST;

        // Payload JSON (fetch API)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw     = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = array_merge($body, $decoded);
            }
        }

        return self::$current = new self($_GET, $body, $_SERVER, $_FILES, $_COOKIE);
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // Method spoofing para formularios HTML (_method=PUT/DELETE)
        if ($method === 'POST' && isset($this->body['_method'])) {
            $spoofed = strtoupper((string) $this->body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri  = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return '/' . trim(rawurldecode($path), '/');
    }

    public function fullUrl(): string
    {
        return url(ltrim($this->path(), '/')) . ($this->query ? '?' . http_build_query($this->query) : '');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function integer(string $key, ?int $default = null): ?int
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? $default : (int) $value;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->input($key);
            }
        }

        return $result;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $file : null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $this->server[$normalized] ?? $this->server[strtoupper(str_replace('-', '_', $key))] ?? $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = explode(',', (string) $this->server[$key])[0];

                return trim($ip);
            }
        }

        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /** ¿Espera JSON? (fetch/AJAX o rutas /api). */
    public function expectsJson(): bool
    {
        return $this->isAjax()
            || str_contains((string) $this->header('Accept'), 'application/json')
            || str_contains((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json')
            || str_starts_with($this->path(), '/api/');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
