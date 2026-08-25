<?php
/**
 * Ruta: /app/Services/System/GitHubService.php
 *
 * Único punto de contacto con la API de GitHub (spec §132). Ninguna vista ni
 * controlador llama directamente a GitHub.
 *
 * SEGURIDAD:
 *   · El token se guarda cifrado (App\Support\Crypto) o llega por variable de
 *     entorno GITHUB_TOKEN. Nunca en texto plano en la base (spec §107).
 *   · El token jamás se devuelve al frontend ni se escribe en logs (§142, §143).
 *   · Se recomienda un fine-grained PAT con Contents:Read y Metadata:Read (§106).
 */

namespace App\Services\System;

use App\Services\SettingService;

final class GitHubService
{
    private const API = 'https://api.github.com';

    public function __construct(
        private readonly int $timeout = 15,
    ) {
    }

    // -----------------------------------------------------------------
    //  Configuración
    // -----------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) setting('github_enabled', config('github.enabled', false));
    }

    public function owner(): string
    {
        return (string) setting('github_owner', config('github.owner', ''));
    }

    public function repository(): string
    {
        return (string) setting('github_repository', config('github.repository', ''));
    }

    public function branch(): string
    {
        return (string) setting('github_branch', config('github.branch', 'main'));
    }

    public function repoFullName(): string
    {
        return trim($this->owner() . '/' . $this->repository(), '/');
    }

    public function repoUrl(): string
    {
        return 'https://github.com/' . $this->repoFullName();
    }

    /** ¿Hay token disponible? (sin exponerlo) */
    public function hasToken(): bool
    {
        return $this->token() !== null;
    }

    /** Pista visible para el panel: "github_pat_****F82K" (spec §109). */
    public function tokenHint(): string
    {
        $hint = (string) setting('github_token_hint', '');

        if ($hint !== '') {
            return $hint;
        }

        return env('GITHUB_TOKEN') !== null ? 'Definido por variable de entorno' : '';
    }

    /**
     * Token en claro. SÓLO para uso interno del servidor al llamar a la API.
     * Prioridad: variable de entorno → secreto cifrado en `settings`.
     */
    private function token(): ?string
    {
        $fromEnv = env('GITHUB_TOKEN');

        if (is_string($fromEnv) && $fromEnv !== '') {
            return $fromEnv;
        }

        $stored = SettingService::getSecret('github_token');

        return ($stored !== null && $stored !== '') ? $stored : null;
    }

    /** Guarda el token cifrado. Devuelve la pista para mostrar en el panel. */
    public function storeToken(string $token, ?int $userId = null): string
    {
        $token = trim($token);

        if ($token === '') {
            throw new \InvalidArgumentException('El token no puede estar vacío.');
        }

        if (!\App\Support\Crypto::isConfigured()) {
            throw new \RuntimeException(
                'APP_KEY no está configurada. Genera una con "php bin/flava key:generate" y guárdala fuera del webroot antes de almacenar secretos.'
            );
        }

        SettingService::putSecret('github_token', $token, $userId, 'github');

        return \App\Support\Crypto::mask($token);
    }

    public function forgetToken(?int $userId = null): void
    {
        SettingService::forgetSecret('github_token', $userId);
    }

    // -----------------------------------------------------------------
    //  Operaciones sobre el repositorio
    // -----------------------------------------------------------------

    /**
     * Comprueba token, repositorio, rama y permisos (spec §115).
     * @return array{ok:bool,message:string,checks:array<string,array{ok:bool,detail:string}>}
     */
    public function testConnection(): array
    {
        $checks = [];

        if (!$this->hasToken()) {
            return [
                'ok'      => false,
                'message' => 'No hay un token configurado.',
                'checks'  => ['token' => ['ok' => false, 'detail' => 'Sin token']],
            ];
        }

        // 1) Token válido
        $user = $this->request('GET', '/user');

        $checks['token'] = $user['ok']
            ? ['ok' => true, 'detail' => 'Token válido' . (isset($user['data']['login']) ? ' (' . $user['data']['login'] . ')' : '')]
            : ['ok' => false, 'detail' => $this->friendlyError($user)];

        // Los fine-grained PAT pueden no tener acceso a /user: se sigue evaluando.
        $repoPath = '/repos/' . $this->repoFullName();
        $repo     = $this->request('GET', $repoPath);

        $checks['repository'] = $repo['ok']
            ? ['ok' => true, 'detail' => 'Repositorio encontrado: ' . ($repo['data']['full_name'] ?? $this->repoFullName())]
            : ['ok' => false, 'detail' => $this->friendlyError($repo)];

        if (!$repo['ok']) {
            return [
                'ok'      => false,
                'message' => 'No fue posible acceder al repositorio. Revisa owner, nombre y permisos del token.',
                'checks'  => $checks,
            ];
        }

        // 2) Rama existente
        $branch = $this->request('GET', $repoPath . '/branches/' . rawurlencode($this->branch()));

        $checks['branch'] = $branch['ok']
            ? ['ok' => true, 'detail' => 'Rama ' . $this->branch() . ' disponible']
            : ['ok' => false, 'detail' => 'La rama "' . $this->branch() . '" no existe en el repositorio'];

        // 3) Permisos mínimos: leer contenidos
        $checks['contents'] = $branch['ok']
            ? ['ok' => true, 'detail' => 'Lectura de contenidos correcta']
            : ['ok' => false, 'detail' => 'Sin acceso de lectura al contenido'];

        $ok = $checks['repository']['ok'] && $checks['branch']['ok'];

        SettingService::set('github_last_check', now()->format('Y-m-d H:i:s'), null, 'github');

        return [
            'ok'      => $ok,
            'message' => $ok
                ? 'Conexión correcta. Repositorio encontrado y rama ' . $this->branch() . ' disponible.'
                : 'La conexión presenta problemas. Revisa el detalle.',
            'checks'  => $checks,
        ];
    }

    /** Último commit remoto de la rama de producción. */
    public function latestCommit(?string $branch = null): ?array
    {
        $branch ??= $this->branch();

        $response = $this->request('GET', sprintf(
            '/repos/%s/commits/%s',
            $this->repoFullName(),
            rawurlencode($branch)
        ));

        if (!$response['ok']) {
            return null;
        }

        $data = $response['data'];

        return [
            'hash'    => $data['sha'] ?? '',
            'short'   => substr((string) ($data['sha'] ?? ''), 0, 7),
            'message' => $data['commit']['message'] ?? '',
            'author'  => $data['commit']['author']['name'] ?? '',
            'date'    => $data['commit']['author']['date'] ?? '',
            'url'     => $data['html_url'] ?? '',
        ];
    }

    /** Commits entre dos referencias (lista de cambios de la actualización). */
    public function compare(string $base, string $head): ?array
    {
        $response = $this->request('GET', sprintf(
            '/repos/%s/compare/%s...%s',
            $this->repoFullName(),
            rawurlencode($base),
            rawurlencode($head)
        ));

        if (!$response['ok']) {
            return null;
        }

        $data = $response['data'];

        return [
            'ahead_by'  => (int) ($data['ahead_by'] ?? 0),
            'behind_by' => (int) ($data['behind_by'] ?? 0),
            'status'    => $data['status'] ?? 'unknown',
            'commits'   => array_map(static fn (array $commit): array => [
                'short'   => substr((string) ($commit['sha'] ?? ''), 0, 7),
                'message' => strtok((string) ($commit['commit']['message'] ?? ''), "\n"),
                'author'  => $commit['commit']['author']['name'] ?? '',
                'date'    => $commit['commit']['author']['date'] ?? '',
            ], $data['commits'] ?? []),
            'files'     => array_column($data['files'] ?? [], 'filename'),
        ];
    }

    public function branches(): array
    {
        $response = $this->request('GET', '/repos/' . $this->repoFullName() . '/branches?per_page=100');

        return $response['ok'] ? array_column($response['data'] ?? [], 'name') : [];
    }

    /** Releases publicados (compatibilidad futura con GitHub Releases, spec §139). */
    public function releases(int $limit = 10): array
    {
        $response = $this->request('GET', '/repos/' . $this->repoFullName() . '/releases?per_page=' . $limit);

        if (!$response['ok']) {
            return [];
        }

        return array_map(static fn (array $release): array => [
            'tag'          => $release['tag_name'] ?? '',
            'name'         => $release['name'] ?? '',
            'body'         => $release['body'] ?? '',
            'published_at' => $release['published_at'] ?? '',
            'zipball_url'  => $release['zipball_url'] ?? '',
        ], $response['data'] ?? []);
    }

    /**
     * Descarga el tarball/zipball de una referencia. Estrategia alternativa
     * cuando el hosting no permite ejecutar Git (spec §112).
     *
     * @return string ruta del archivo descargado
     */
    public function downloadArchive(string $ref, string $destination): string
    {
        $url = sprintf('%s/repos/%s/zipball/%s', self::API, $this->repoFullName(), rawurlencode($ref));

        $handle = @fopen($destination, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo de destino.');
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE           => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_USERAGENT      => $this->userAgent(),
        ]);

        $success = curl_exec($curl);
        $status  = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error   = curl_error($curl);

        curl_close($curl);
        fclose($handle);

        if ($success === false || $status >= 400) {
            @unlink($destination);
            throw new \RuntimeException('No se pudo descargar la actualización desde GitHub (HTTP ' . $status . ') ' . $error);
        }

        return $destination;
    }

    // -----------------------------------------------------------------
    //  Cliente HTTP
    // -----------------------------------------------------------------

    /**
     * @return array{ok:bool,status:int,data:array,error:?string}
     */
    private function request(string $method, string $path, array $body = []): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'La extensión cURL no está disponible en este servidor.'];
        }

        $url  = str_starts_with($path, 'http') ? $path : self::API . $path;
        $curl = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_USERAGENT      => $this->userAgent(),
        ];

        if ($body !== []) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($curl, $options);

        $raw    = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error  = curl_error($curl);

        curl_close($curl);

        if ($raw === false) {
            // El mensaje de cURL nunca incluye el token, pero se sanea igual.
            logger()->error('Error de conexión con GitHub', ['error' => $error, 'status' => $status]);

            return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'No se pudo conectar con GitHub: ' . $error];
        }

        $data = json_decode((string) $raw, true);

        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'data'   => is_array($data) ? $data : [],
            'error'  => is_array($data) ? ($data['message'] ?? null) : null,
        ];
    }

    /** @return array<int,string> */
    private function headers(): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $token = $this->token();

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }

    private function userAgent(): string
    {
        return 'FlavaStudio/' . config('version.version', '1.0.0');
    }

    private function friendlyError(array $response): string
    {
        return match ($response['status']) {
            401     => 'Token inválido o expirado',
            403     => 'El token no tiene permisos suficientes (se requiere Contents: Read)',
            404     => 'No encontrado: revisa el owner y el nombre del repositorio',
            0       => (string) ($response['error'] ?? 'Sin conexión'),
            default => (string) ($response['error'] ?? 'Error HTTP ' . $response['status']),
        };
    }
}
