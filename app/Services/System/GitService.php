<?php
/**
 * Ruta: /app/Services/System/GitService.php
 *
 * Ejecuta ÚNICAMENTE operaciones Git predefinidas (spec §113, §134).
 * NUNCA se acepta un comando escrito por el usuario: todos los argumentos
 * variables (rama, remoto, commit) se validan contra listas blancas y se
 * escapan con escapeshellarg().
 */

namespace App\Services\System;

final class GitService
{
    private string $repoPath;

    public function __construct(?string $repoPath = null)
    {
        $this->repoPath = rtrim($repoPath ?? (string) (setting('github_repo_path', null) ?? config('github.repo_path', BASE_PATH)), '/');
    }

    public function repoPath(): string
    {
        return $this->repoPath;
    }

    // -----------------------------------------------------------------
    //  Disponibilidad
    // -----------------------------------------------------------------

    /** ¿Se pueden ejecutar comandos del sistema desde PHP? (spec §112) */
    public function shellAvailable(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    public function isAvailable(): bool
    {
        return $this->shellAvailable() && $this->version() !== null;
    }

    public function version(): ?string
    {
        $result = $this->run(['--version'], false);

        if (!$result['ok']) {
            return null;
        }

        return preg_match('/(\d+\.\d+(\.\d+)?)/', $result['output'], $m) ? $m[1] : trim($result['output']);
    }

    public function isRepository(): bool
    {
        return is_dir($this->repoPath . '/.git')
            && $this->run(['rev-parse', '--is-inside-work-tree'])['ok'];
    }

    // -----------------------------------------------------------------
    //  Lectura del estado
    // -----------------------------------------------------------------

    public function getCurrentBranch(): ?string
    {
        $result = $this->run(['rev-parse', '--abbrev-ref', 'HEAD']);

        return $result['ok'] ? trim($result['output']) : null;
    }

    public function getCurrentCommit(bool $short = false): ?string
    {
        $result = $this->run($short ? ['rev-parse', '--short', 'HEAD'] : ['rev-parse', 'HEAD']);

        return $result['ok'] ? trim($result['output']) : null;
    }

    /** @return array{hash:string,short:string,message:string,author:string,date:string}|null */
    public function getLastCommit(): ?array
    {
        $result = $this->run(['log', '-1', '--pretty=format:%H%x1f%h%x1f%s%x1f%an%x1f%cI']);

        if (!$result['ok'] || trim($result['output']) === '') {
            return null;
        }

        $parts = explode("\x1f", trim($result['output']));

        if (count($parts) < 5) {
            return null;
        }

        return [
            'hash'    => $parts[0],
            'short'   => $parts[1],
            'message' => $parts[2],
            'author'  => $parts[3],
            'date'    => $parts[4],
        ];
    }

    /** ¿Hay archivos modificados directamente en producción? (spec §136) */
    public function status(): array
    {
        $result = $this->run(['status', '--porcelain']);

        if (!$result['ok']) {
            return ['clean' => false, 'files' => [], 'error' => $result['output']];
        }

        $files = array_values(array_filter(array_map('trim', explode("\n", $result['output']))));

        return ['clean' => $files === [], 'files' => $files, 'error' => null];
    }

    public function hasLocalChanges(): bool
    {
        return !$this->status()['clean'];
    }

    public function getRemoteUrl(string $remote = 'origin'): ?string
    {
        $this->assertSafeName($remote, 'remoto');
        $result = $this->run(['remote', 'get-url', $remote]);

        if (!$result['ok']) {
            return null;
        }

        // Nunca devolver credenciales embebidas en la URL.
        return preg_replace('#https://[^@/]+@#', 'https://', trim($result['output']));
    }

    /** Descarga referencias remotas sin modificar el árbol de trabajo. */
    public function fetch(string $remote = 'origin', ?string $branch = null): array
    {
        $this->assertSafeName($remote, 'remoto');

        $args = ['fetch', $remote];

        if ($branch !== null) {
            $this->assertSafeName($branch, 'rama');
            $args[] = $branch;
        }

        $args[] = '--prune';

        return $this->run($args);
    }

    public function getRemoteCommit(string $branch, string $remote = 'origin'): ?string
    {
        $this->assertSafeName($remote, 'remoto');
        $this->assertSafeName($branch, 'rama');

        $result = $this->run(['rev-parse', $remote . '/' . $branch]);

        return $result['ok'] ? trim($result['output']) : null;
    }

    /** Commits presentes en el remoto que aún no están en local. */
    public function pendingCommits(string $branch, string $remote = 'origin', int $limit = 30): array
    {
        $this->assertSafeName($remote, 'remoto');
        $this->assertSafeName($branch, 'rama');

        $result = $this->run([
            'log',
            'HEAD..' . $remote . '/' . $branch,
            '--pretty=format:%h%x1f%s%x1f%an%x1f%cI',
            '-n', (string) $limit,
        ]);

        if (!$result['ok'] || trim($result['output']) === '') {
            return [];
        }

        $commits = [];

        foreach (explode("\n", trim($result['output'])) as $line) {
            $parts = explode("\x1f", $line);

            if (count($parts) >= 4) {
                $commits[] = [
                    'short'   => $parts[0],
                    'message' => $parts[1],
                    'author'  => $parts[2],
                    'date'    => $parts[3],
                ];
            }
        }

        return $commits;
    }

    /** Archivos que cambiarían al actualizar (para saber si hay migraciones). */
    public function changedFiles(string $fromCommit, string $toRef): array
    {
        $this->assertSafeCommit($fromCommit);
        $this->assertSafeRef($toRef);

        $result = $this->run(['diff', '--name-only', $fromCommit, $toRef]);

        if (!$result['ok']) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $result['output']))));
    }

    // -----------------------------------------------------------------
    //  Escritura (sólo las operaciones permitidas)
    // -----------------------------------------------------------------

    /** Actualiza el árbol de trabajo al estado del remoto. */
    public function pull(string $branch, string $remote = 'origin'): array
    {
        $this->assertSafeName($remote, 'remoto');
        $this->assertSafeName($branch, 'rama');

        return $this->run(['merge', '--ff-only', $remote . '/' . $branch]);
    }

    /** Vuelve a un commit concreto (rollback). Requiere confirmación previa. */
    public function checkoutCommit(string $commit): array
    {
        $this->assertSafeCommit($commit);

        return $this->run(['checkout', '--force', $commit]);
    }

    public function checkoutBranch(string $branch): array
    {
        $this->assertSafeName($branch, 'rama');

        return $this->run(['checkout', $branch]);
    }

    // -----------------------------------------------------------------
    //  Núcleo de ejecución
    // -----------------------------------------------------------------

    /**
     * Ejecuta git con argumentos ya validados.
     * @param array<int,string> $args
     * @return array{ok:bool,output:string,code:int}
     */
    private function run(array $args, bool $inRepo = true): array
    {
        if (!$this->shellAvailable()) {
            return ['ok' => false, 'output' => 'La ejecución de comandos está deshabilitada en este servidor.', 'code' => -1];
        }

        $command = 'git';

        if ($inRepo) {
            $command .= ' -C ' . escapeshellarg($this->repoPath);
        }

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $env         = [
            'PATH'               => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'HOME'               => getenv('HOME') ?: sys_get_temp_dir(),
            'GIT_TERMINAL_PROMPT' => '0',   // nunca pedir credenciales de forma interactiva
            'LC_ALL'             => 'C',
        ];

        $process = @proc_open($command, $descriptors, $pipes, $this->repoPath, $env);

        if (!is_resource($process)) {
            return ['ok' => false, 'output' => 'No fue posible ejecutar git.', 'code' => -1];
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $code   = proc_close($process);
        $output = trim($stdout . ($stderr !== '' ? "\n" . $stderr : ''));

        // El log jamás debe contener tokens.
        logger()->deploy('git ' . implode(' ', $args), ['code' => $code]);

        return [
            'ok'     => $code === 0,
            'output' => $this->sanitize($output),
            'code'   => $code,
        ];
    }

    /** Elimina cualquier credencial que un mensaje de error pudiera exponer. */
    private function sanitize(string $output): string
    {
        return (string) preg_replace(
            ['#https://[^@\s/]+@#', '/(github_pat_|ghp_|gho_|ghs_)[A-Za-z0-9_]+/'],
            ['https://', '$1***'],
            $output
        );
    }

    /** Ramas y remotos: sólo caracteres seguros (spec §135). */
    private function assertSafeName(string $value, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9._\/\-]{1,100}$/', $value) || str_contains($value, '..')) {
            throw new \InvalidArgumentException("Nombre de {$label} inválido.");
        }
    }

    private function assertSafeCommit(string $commit): void
    {
        if (!preg_match('/^[0-9a-f]{7,40}$/i', $commit)) {
            throw new \InvalidArgumentException('Hash de commit inválido.');
        }
    }

    private function assertSafeRef(string $ref): void
    {
        if (preg_match('/^[0-9a-f]{7,40}$/i', $ref)) {
            return;
        }

        $this->assertSafeName($ref, 'referencia');
    }
}
