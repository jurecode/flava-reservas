<?php
/**
 * Ruta: /app/Services/System/DeploymentService.php
 *
 * Orquesta la actualización controlada del servidor (spec §118, §133).
 *
 * FLUJO OFICIAL:
 *   1. verificar permisos        7. descargar actualización
 *   2. comprobar repositorio     8. aplicar archivos
 *   3. verificar branch          9. ejecutar migraciones
 *   4. comprobar cambios locales 10. limpiar cache
 *   5. crear respaldo            11. verificaciones
 *   6. activar mantención        12. desactivar mantención
 *                                13. registrar resultado
 *
 * NUNCA ejecuta un `git pull` a ciegas y NUNCA sobrescribe configuración,
 * uploads ni la base de datos (spec §119, §121, §122).
 */

namespace App\Services\System;

use App\Models\Deployment;
use App\Services\ActivityLogger;
use App\Services\SettingService;

final class DeploymentService
{
    /** Rutas que jamás se tocan durante un despliegue (spec §119). */
    public const PROTECTED_PATHS = [
        '.env',
        'config/secrets.php',
        'config/database.php',
        'storage/',
        'public/uploads/',
    ];

    public function __construct(
        private readonly GitService $git = new GitService(),
        private readonly GitHubService $github = new GitHubService(),
        private readonly BackupService $backup = new BackupService(),
        private readonly MigrationService $migrations = new MigrationService(),
        private readonly MaintenanceService $maintenance = new MaintenanceService(),
        private readonly Deployment $deployments = new Deployment(),
    ) {
    }

    // -----------------------------------------------------------------
    //  Estado del sistema
    // -----------------------------------------------------------------

    /** Panorama completo para el panel SUPER_ADMIN. */
    public function systemStatus(): array
    {
        $gitAvailable = $this->git->isAvailable();
        $isRepo       = $gitAvailable && $this->git->isRepository();

        return [
            'version'          => config('version.version'),
            'codename'         => config('version.codename'),
            'environment'      => config('app.env'),
            'php_version'      => PHP_VERSION,
            'server'           => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
            'database'         => config('database.database'),
            'timezone'         => config('app.timezone'),
            'git_available'    => $gitAvailable,
            'git_version'      => $gitAvailable ? $this->git->version() : null,
            'shell_available'  => $this->git->shellAvailable(),
            'is_repository'    => $isRepo,
            'repo_path'        => $this->git->repoPath(),
            'current_branch'   => $isRepo ? $this->git->getCurrentBranch() : null,
            'current_commit'   => $isRepo ? $this->git->getCurrentCommit(true) : null,
            'last_commit'      => $isRepo ? $this->git->getLastCommit() : null,
            'local_changes'    => $isRepo ? $this->git->status() : ['clean' => true, 'files' => []],
            'remote_url'       => $isRepo ? $this->git->getRemoteUrl() : null,
            'github_enabled'   => $this->github->isEnabled(),
            'github_repo'      => $this->github->repoFullName(),
            'github_branch'    => $this->github->branch(),
            'github_token'     => $this->github->hasToken(),
            'token_hint'       => $this->github->tokenHint(),
            'crypto_ready'     => \App\Support\Crypto::isConfigured(),
            'maintenance'      => $this->maintenance->isEnabled(),
            'pending_migrations' => $this->migrations->pending(),
            'last_check'       => setting('github_last_check', ''),
            'last_sync'        => setting('github_last_sync', ''),
            'strategy'         => $this->strategy(),
            'writable'         => $this->writabilityReport(),
        ];
    }

    /** Estrategia efectiva: git si está disponible; si no, ZIP vía API. */
    public function strategy(): string
    {
        $configured = (string) config('github.strategy', 'auto');

        if ($configured !== 'auto') {
            return $configured;
        }

        return ($this->git->isAvailable() && $this->git->isRepository()) ? 'git' : 'zip';
    }

    private function writabilityReport(): array
    {
        $paths = [
            'storage/logs'    => STORAGE_PATH . '/logs',
            'storage/backups' => STORAGE_PATH . '/backups',
            'storage/cache'   => STORAGE_PATH . '/cache',
            'storage/framework' => STORAGE_PATH . '/framework',
            'public/uploads'  => PUBLIC_PATH . '/uploads',
        ];

        $report = [];

        foreach ($paths as $label => $path) {
            $report[$label] = is_dir($path) && is_writable($path);
        }

        return $report;
    }

    // -----------------------------------------------------------------
    //  Buscar actualizaciones (spec §116)
    // -----------------------------------------------------------------

    /**
     * Compara el commit local con el último del remoto.
     *
     * @return array{available:bool,message:string,local:?string,remote:?string,commits:array,files:array,has_migrations:bool}
     */
    public function checkForUpdates(): array
    {
        $empty = [
            'available'      => false,
            'message'        => '',
            'local'          => null,
            'remote'         => null,
            'commits'        => [],
            'files'          => [],
            'has_migrations' => false,
        ];

        if (!$this->github->isEnabled()) {
            return array_merge($empty, ['message' => 'La integración con GitHub está desactivada.']);
        }

        if (!$this->github->hasToken() && $this->strategy() === 'zip') {
            return array_merge($empty, ['message' => 'Configura un token de GitHub para buscar actualizaciones.']);
        }

        $remoteCommit = null;
        $localCommit  = null;
        $commits      = [];
        $files        = [];

        // Preferimos la API: funciona incluso sin Git en el servidor.
        $remote = $this->github->latestCommit();

        if ($remote === null) {
            return array_merge($empty, ['message' => 'No fue posible consultar GitHub. Revisa la conexión y el token.']);
        }

        $remoteCommit = $remote['hash'];

        if ($this->git->isRepository()) {
            $localCommit = $this->git->getCurrentCommit();
        } else {
            $localCommit = (string) setting('installed_commit', '');
        }

        SettingService::set('github_last_check', now()->format('Y-m-d H:i:s'), null, 'github');

        if ($localCommit === '' || $localCommit === null) {
            return array_merge($empty, [
                'available' => true,
                'message'   => 'No hay un commit local registrado. La primera sincronización debe hacerse manualmente.',
                'remote'    => $remote['short'],
                'commits'   => [$remote],
            ]);
        }

        if (hash_equals($localCommit, $remoteCommit)) {
            return array_merge($empty, [
                'message' => 'El sistema está actualizado.',
                'local'   => substr($localCommit, 0, 7),
                'remote'  => $remote['short'],
            ]);
        }

        $comparison = $this->github->compare($localCommit, $remoteCommit);

        if ($comparison !== null) {
            $commits = $comparison['commits'];
            $files   = $comparison['files'];
        }

        $hasMigrations = false;
        foreach ($files as $file) {
            if (str_starts_with($file, 'database/migrations/')) {
                $hasMigrations = true;
                break;
            }
        }

        return [
            'available'      => true,
            'message'        => 'Hay una actualización disponible.',
            'local'          => substr($localCommit, 0, 7),
            'remote'         => $remote['short'],
            'remote_full'    => $remoteCommit,
            'commits'        => $commits ?: [$remote],
            'files'          => $files,
            'has_migrations' => $hasMigrations,
        ];
    }

    // -----------------------------------------------------------------
    //  Despliegue
    // -----------------------------------------------------------------

    /**
     * Ejecuta la actualización completa de forma controlada.
     *
     * @param bool $force ignora los cambios locales del servidor (peligroso)
     * @return array{ok:bool,message:string,deployment_id:?int,steps:array<int,array{step:string,ok:bool,detail:string}>}
     */
    public function deploy(?int $userId = null, bool $force = false): array
    {
        $steps  = [];
        $record = static function (array &$steps, string $step, bool $ok, string $detail = ''): void {
            $steps[] = ['step' => $step, 'ok' => $ok, 'detail' => $detail];
            logger()->deploy(($ok ? '[OK] ' : '[FALLO] ') . $step . ($detail !== '' ? ' — ' . $detail : ''));
        };

        // ── 1. Precondiciones ──────────────────────────────────────────
        if ($this->deployments->isRunning()) {
            return ['ok' => false, 'message' => 'Ya hay un despliegue en curso.', 'deployment_id' => null, 'steps' => $steps];
        }

        if (!$this->github->isEnabled()) {
            return ['ok' => false, 'message' => 'La integración con GitHub está desactivada.', 'deployment_id' => null, 'steps' => $steps];
        }

        $strategy = $this->strategy();

        if ($strategy === 'zip') {
            return [
                'ok'      => false,
                'message' => 'Este servidor no permite operaciones Git desde PHP. La actualización por paquete ZIP se habilitará en una etapa posterior; por ahora actualiza el código manualmente y ejecuta las migraciones desde este panel.',
                'deployment_id' => null,
                'steps'   => $steps,
            ];
        }

        $record($steps, 'Verificar permisos', true, 'SUPER_ADMIN autenticado');

        // ── 2 y 3. Repositorio y rama ─────────────────────────────────
        if (!$this->git->isRepository()) {
            $record($steps, 'Comprobar repositorio', false, 'El directorio no es un repositorio Git');

            return ['ok' => false, 'message' => 'El servidor no tiene un repositorio Git inicializado.', 'deployment_id' => null, 'steps' => $steps];
        }

        $record($steps, 'Comprobar repositorio', true, (string) $this->git->getRemoteUrl());

        $branch        = $this->github->branch();
        $currentBranch = $this->git->getCurrentBranch();

        if ($currentBranch !== $branch) {
            $record($steps, 'Verificar rama', false, "El servidor está en «{$currentBranch}» y se esperaba «{$branch}»");

            return ['ok' => false, 'message' => "El servidor está en la rama «{$currentBranch}». Cámbiala antes de actualizar.", 'deployment_id' => null, 'steps' => $steps];
        }

        $record($steps, 'Verificar rama', true, $branch);

        // ── 4. Cambios locales (spec §136) ────────────────────────────
        $status = $this->git->status();

        if (!$status['clean'] && !$force) {
            $record($steps, 'Comprobar cambios locales', false, count($status['files']) . ' archivo(s) modificados en el servidor');

            return [
                'ok'      => false,
                'message' => 'Existen modificaciones locales en el servidor. Revísalas antes de actualizar: el despliegue no las sobrescribirá automáticamente.',
                'deployment_id' => null,
                'steps'   => $steps,
            ];
        }

        $record($steps, 'Comprobar cambios locales', true, $status['clean'] ? 'Árbol limpio' : 'Forzado por el administrador');

        $previousCommit = (string) $this->git->getCurrentCommit();

        $deploymentId = $this->deployments->start([
            'version'         => config('version.version'),
            'previous_commit' => $previousCommit,
            'branch'          => $branch,
            'strategy'        => 'git',
            'started_by'      => $userId,
        ]);

        ActivityLogger::log('deploy.started', 'deployment', $deploymentId, 'Despliegue iniciado desde ' . substr($previousCommit, 0, 7));

        $backupName = null;

        try {
            // ── 5. Respaldo ────────────────────────────────────────────
            if ((bool) setting('deploy_auto_backup', true)) {
                $backup     = $this->backup->create('deploy', ['from_commit' => $previousCommit]);
                $backupName = $backup['name'];
                $record($steps, 'Crear respaldo', true, $backup['name'] . ' (' . BackupService::humanSize($backup['size']) . ')');
                $this->deployments->update($deploymentId, ['backup_path' => $backupName]);
            } else {
                $record($steps, 'Crear respaldo', true, 'Omitido por configuración');
            }

            // ── 6. Mantención ──────────────────────────────────────────
            $useMaintenance = (bool) setting('deploy_maintenance', true);

            if ($useMaintenance) {
                $this->maintenance->enable('Estamos actualizando Flava Studio. Volvemos en unos minutos.');
                $record($steps, 'Activar mantención', true);
            }

            // ── 7. Descargar ───────────────────────────────────────────
            $fetch = $this->git->fetch('origin', $branch);

            if (!$fetch['ok']) {
                throw new \RuntimeException('No se pudo obtener la actualización: ' . $fetch['output']);
            }

            $remoteCommit = (string) $this->git->getRemoteCommit($branch);
            $record($steps, 'Descargar actualización', true, 'origin/' . $branch . ' @ ' . substr($remoteCommit, 0, 7));

            if (hash_equals($previousCommit, $remoteCommit)) {
                $this->maintenance->disable();
                $this->deployments->finish($deploymentId, Deployment::SUCCESS, [
                    'commit_hash' => $remoteCommit,
                    'notes'       => 'Sin cambios: el sistema ya estaba actualizado.',
                ]);
                $record($steps, 'Aplicar archivos', true, 'Sin cambios pendientes');

                return ['ok' => true, 'message' => 'El sistema ya estaba actualizado.', 'deployment_id' => $deploymentId, 'steps' => $steps];
            }

            // ── 8. Aplicar ─────────────────────────────────────────────
            $pull = $this->git->pull($branch);

            if (!$pull['ok']) {
                throw new \RuntimeException('No se pudieron aplicar los archivos: ' . $pull['output']);
            }

            $newCommit = (string) $this->git->getCurrentCommit();
            $record($steps, 'Aplicar archivos', true, substr($previousCommit, 0, 7) . ' → ' . substr($newCommit, 0, 7));

            // ── 9. Migraciones ─────────────────────────────────────────
            $migrationResult = $this->migrations->run();

            if ($migrationResult['failed'] !== null) {
                throw new \RuntimeException(
                    'Falló la migración ' . $migrationResult['failed'] . ': ' . $migrationResult['error']
                );
            }

            $migrationsRun = count($migrationResult['executed']);
            $record(
                $steps,
                'Ejecutar migraciones',
                true,
                $migrationsRun > 0 ? $migrationsRun . ' aplicada(s)' : 'Sin migraciones pendientes'
            );

            // ── 10. Cache ──────────────────────────────────────────────
            $this->clearCache();
            $record($steps, 'Limpiar cache', true);

            // ── 11. Verificaciones ─────────────────────────────────────
            $verification = $this->verify();
            $record($steps, 'Verificar sistema', $verification['ok'], $verification['detail']);

            if (!$verification['ok']) {
                throw new \RuntimeException('Verificación posterior fallida: ' . $verification['detail']);
            }

            // ── 12. Desactivar mantención ──────────────────────────────
            if ($useMaintenance) {
                $this->maintenance->disable();
                $record($steps, 'Desactivar mantención', true);
            }

            // ── 13. Registrar ──────────────────────────────────────────
            SettingService::set('installed_commit', $newCommit, $userId, 'github');
            SettingService::set('github_last_sync', now()->format('Y-m-d H:i:s'), $userId, 'github');

            $this->deployments->finish($deploymentId, Deployment::SUCCESS, [
                'commit_hash'    => $newCommit,
                'version'        => config('version.version'),
                'migrations_run' => $migrationsRun,
                'notes'          => 'Despliegue completado correctamente.',
            ]);

            ActivityLogger::log('deploy.success', 'deployment', $deploymentId, 'Despliegue exitoso a ' . substr($newCommit, 0, 7));

            return [
                'ok'            => true,
                'message'       => 'Actualización aplicada correctamente.',
                'deployment_id' => $deploymentId,
                'steps'         => $steps,
            ];
        } catch (\Throwable $e) {
            $record($steps, 'Error', false, $e->getMessage());

            $this->maintenance->disable();
            $this->deployments->finish($deploymentId, Deployment::FAILED, [
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'backup_path'   => $backupName,
            ]);

            ActivityLogger::log('deploy.failed', 'deployment', $deploymentId, mb_substr($e->getMessage(), 0, 200));
            logger()->error('Despliegue fallido', ['deployment' => $deploymentId, 'error' => $e->getMessage()]);

            return [
                'ok'            => false,
                'message'       => 'El despliegue falló: ' . $e->getMessage(),
                'deployment_id' => $deploymentId,
                'steps'         => $steps,
            ];
        }
    }

    /**
     * Vuelve a una versión anterior (spec §130).
     * Sólo revierte ARCHIVOS: los cambios de base de datos deben analizarse
     * manualmente, por eso se advierte al SUPER_ADMIN antes de confirmar.
     */
    public function rollback(int $deploymentId, ?int $userId = null): array
    {
        $deployment = $this->deployments->find($deploymentId);

        if ($deployment === null) {
            return ['ok' => false, 'message' => 'El despliegue indicado no existe.'];
        }

        $target = (string) $deployment['previous_commit'];

        if ($target === '') {
            return ['ok' => false, 'message' => 'Ese despliegue no registró un commit anterior al que volver.'];
        }

        if (!$this->git->isRepository()) {
            return ['ok' => false, 'message' => 'El rollback automático requiere Git en el servidor.'];
        }

        try {
            $this->maintenance->enable('Estamos restaurando una versión anterior.');
            $this->backup->create('rollback', ['deployment_id' => $deploymentId]);

            $result = $this->git->checkoutCommit($target);

            if (!$result['ok']) {
                throw new \RuntimeException($result['output']);
            }

            $this->clearCache();
            SettingService::set('installed_commit', $target, $userId, 'github');

            $this->deployments->update($deploymentId, ['status' => Deployment::ROLLED_BACK]);
            $this->maintenance->disable();

            ActivityLogger::log('deploy.rollback', 'deployment', $deploymentId, 'Rollback a ' . substr($target, 0, 7));

            return [
                'ok'      => true,
                'message' => 'Se restauró la versión ' . substr($target, 0, 7) . '. Revisa si alguna migración de base de datos requiere atención manual.',
            ];
        } catch (\Throwable $e) {
            $this->maintenance->disable();
            logger()->error('Rollback fallido', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'No se pudo restaurar: ' . $e->getMessage()];
        }
    }

    /** Verificaciones posteriores al despliegue. */
    public function verify(): array
    {
        $problems = [];

        if (!\Core\Database::instance()->isConnected()) {
            $problems[] = 'sin conexión a la base de datos';
        }

        foreach (['users', 'bookings', 'settings'] as $table) {
            if (!\Core\Database::instance()->tableExists($table)) {
                $problems[] = "falta la tabla {$table}";
            }
        }

        foreach ([APP_PATH, CORE_PATH, PUBLIC_PATH . '/index.php'] as $path) {
            if (!file_exists($path)) {
                $problems[] = 'falta ' . basename($path);
            }
        }

        return [
            'ok'     => $problems === [],
            'detail' => $problems === [] ? 'Base de datos y archivos correctos' : implode(', ', $problems),
        ];
    }

    public function clearCache(): void
    {
        $directory = STORAGE_PATH . '/cache';

        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                @unlink($file);
            }
        }

        \App\Services\SettingService::flush();

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** Historial para el panel. */
    public function history(int $limit = 25): array
    {
        return $this->deployments->history($limit);
    }

    /** Changelog leído desde CHANGELOG.md (spec §141). */
    public function changelog(int $limitEntries = 5): array
    {
        $file = BASE_PATH . '/CHANGELOG.md';

        if (!is_file($file)) {
            return [];
        }

        $content = (string) file_get_contents($file);
        $entries = [];
        $blocks  = preg_split('/^##\s+/m', $content) ?: [];

        foreach (array_slice($blocks, 1, $limitEntries) as $block) {
            $lines   = explode("\n", trim($block));
            $heading = array_shift($lines) ?? '';

            $entries[] = [
                'version' => trim(strtok($heading, ' —-') ?: $heading),
                'heading' => trim($heading),
                'body'    => trim(implode("\n", $lines)),
            ];
        }

        return $entries;
    }
}
