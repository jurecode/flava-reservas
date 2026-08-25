<?php
/**
 * Ruta: /app/Controllers/SuperAdmin/GitHubController.php
 *
 * Panel «Sistema → GitHub y Actualizaciones» (spec §104).
 *
 * REGLAS DE SEGURIDAD APLICADAS:
 *   · El token NUNCA se devuelve al navegador: sólo su pista (§109).
 *   · Se guarda cifrado con APP_KEY que vive fuera del webroot (§108, §110).
 *   · No existe ningún campo para escribir comandos Git arbitrarios (§113).
 *   · Todas las acciones quedan registradas en activity_logs (§131).
 */

namespace App\Controllers\SuperAdmin;

use App\Models\Deployment;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use App\Services\System\DeploymentService;
use App\Services\System\GitHubService;
use App\Support\Crypto;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class GitHubController extends Controller
{
    public function index(): Response
    {
        $deployment = new DeploymentService();
        $github     = new GitHubService();

        return $this->view('superadmin.github.index', [
            'title'       => 'GitHub y Actualizaciones',
            'active'      => 'github',
            'status'      => $deployment->systemStatus(),
            'github'      => [
                'enabled'    => $github->isEnabled(),
                'owner'      => $github->owner(),
                'repository' => $github->repository(),
                'branch'     => $github->branch(),
                'repo_url'   => $github->repoFullName() !== '' ? $github->repoUrl() : '',
                'has_token'  => $github->hasToken(),
                'token_hint' => $github->tokenHint(),
                'env_token'  => env('GITHUB_TOKEN') !== null,
            ],
            'crypto_ready' => Crypto::isConfigured(),
            'deployments'  => (new Deployment())->history(5),
            'updates'      => Session::getFlash('_update_check'),
            'connection'   => Session::getFlash('_connection_test'),
            'branches'     => Session::getFlash('_branches', []),
        ]);
    }

    /** Guarda owner, repositorio, rama y opciones de despliegue (spec §145). */
    public function saveConfig(): Response
    {
        $validator = new Validator($this->request->all(), [
            'github_owner'      => 'nullable|max:100|regex:/^[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38}$/',
            'github_repository' => 'nullable|max:120|regex:/^[A-Za-z0-9._\-]+$/',
            'github_branch'     => 'nullable|max:100|regex:/^[A-Za-z0-9._\/\-]+$/',
        ], [
            'github_owner'      => 'El owner de GitHub no tiene un formato válido.',
            'github_repository' => 'El nombre del repositorio no tiene un formato válido.',
            'github_branch'     => 'El nombre de la rama no tiene un formato válido.',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $userId = Auth::id();
        $before = [
            'github_owner'      => setting('github_owner', ''),
            'github_repository' => setting('github_repository', ''),
            'github_branch'     => setting('github_branch', 'main'),
            'github_enabled'    => setting('github_enabled', false) ? '1' : '0',
        ];

        SettingService::setMany([
            'github_owner'        => (string) $this->request->input('github_owner'),
            'github_repository'   => (string) $this->request->input('github_repository'),
            'github_branch'       => (string) ($this->request->input('github_branch') ?: 'main'),
            'github_enabled'      => $this->request->boolean('github_enabled') ? 1 : 0,
            'deploy_auto_backup'  => $this->request->boolean('deploy_auto_backup') ? 1 : 0,
            'deploy_maintenance'  => $this->request->boolean('deploy_maintenance') ? 1 : 0,
        ], $userId, 'github');

        ActivityLogger::log(
            'github.connected',
            'settings',
            null,
            'Configuración de GitHub actualizada',
            $before,
            [
                'github_owner'      => (string) $this->request->input('github_owner'),
                'github_repository' => (string) $this->request->input('github_repository'),
                'github_branch'     => (string) $this->request->input('github_branch'),
            ]
        );

        return $this->redirectWith('/super-admin/github', 'Configuración guardada.');
    }

    /**
     * Recibe el token por HTTPS, lo valida contra GitHub y lo guarda cifrado.
     * Nunca vuelve a mostrarse completo.
     */
    public function saveToken(): Response
    {
        $token = trim((string) $this->request->input('github_token'));

        if ($token === '') {
            Session::flash('error', 'Ingresa el token antes de guardar.');

            return $this->back('/super-admin/github');
        }

        if (!Crypto::isConfigured()) {
            Session::flash(
                'error',
                'Falta APP_KEY. Genera una con «php bin/flava key:generate» y guárdala en /.env o /config/secrets.php (fuera del webroot) antes de almacenar el token.'
            );

            return $this->back('/super-admin/github');
        }

        // Acción sensible: exige contraseña reciente (spec §131).
        if (!$this->confirmSensitiveAction()) {
            return $this->back('/super-admin/github');
        }

        $github = new GitHubService();

        try {
            $hint = $github->storeToken($token, Auth::id());
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return $this->back('/super-admin/github');
        }

        // Verifica de inmediato que el token sirve.
        $test = $github->testConnection();

        ActivityLogger::log('github.token_updated', 'settings', null, 'Token de GitHub actualizado (' . $hint . ')');

        Session::flash(
            $test['ok'] ? 'success' : 'error',
            $test['ok']
                ? 'Token guardado y verificado. ' . $test['message']
                : 'Token guardado, pero la verificación falló: ' . $test['message']
        );

        Session::flash('_connection_test', $test);

        return $this->redirect('/super-admin/github');
    }

    public function deleteToken(): Response
    {
        if (!$this->confirmSensitiveAction()) {
            return $this->back('/super-admin/github');
        }

        (new GitHubService())->forgetToken(Auth::id());
        ActivityLogger::log('github.token_removed', 'settings', null, 'Token de GitHub eliminado');

        return $this->redirectWith('/super-admin/github', 'Token eliminado.');
    }

    /** Botón «PROBAR CONEXIÓN» (spec §115). */
    public function testConnection(): Response
    {
        $github = new GitHubService();
        $test   = $github->testConnection();

        if ($this->request->expectsJson()) {
            return $test['ok']
                ? $this->success($test['message'], $test['checks'])
                : $this->fail($test['message'], $test['checks'], 200);
        }

        Session::flash('_connection_test', $test);
        Session::flash('_branches', $test['ok'] ? $github->branches() : []);
        Session::flash($test['ok'] ? 'success' : 'error', $test['message']);

        return $this->redirect('/super-admin/github');
    }

    /** Botón «BUSCAR ACTUALIZACIONES» (spec §116). */
    public function checkUpdates(): Response
    {
        $result = (new DeploymentService())->checkForUpdates();

        if ($this->request->expectsJson()) {
            return $this->success($result['message'], $result);
        }

        Session::flash('_update_check', $result);
        Session::flash($result['available'] ? 'info' : 'success', $result['message']);

        return $this->redirect('/super-admin/github');
    }

    /**
     * Acciones sensibles: contraseña confirmada en los últimos 15 minutos, o
     * la contraseña enviada en el propio formulario.
     */
    private function confirmSensitiveAction(): bool
    {
        if ($this->request->filled('confirm_password')) {
            if (Auth::confirmPassword((string) $this->request->input('confirm_password'))) {
                return true;
            }

            Session::flash('error', 'La contraseña ingresada no es correcta.');

            return false;
        }

        if (Auth::recentlyConfirmed()) {
            return true;
        }

        Session::flash('error', 'Por seguridad, confirma tu contraseña para realizar esta acción.');

        return false;
    }
}
