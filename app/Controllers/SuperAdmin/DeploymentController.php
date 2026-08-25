<?php
/**
 * Ruta: /app/Controllers/SuperAdmin/DeploymentController.php
 *
 * Ejecuta y audita los despliegues (spec §117, §128, §130, §131).
 * El botón «Actualizar producción» es exclusivo del SUPER_ADMIN, exige CSRF,
 * confirmación de contraseña y queda registrado en activity_logs.
 */

namespace App\Controllers\SuperAdmin;

use App\Models\Deployment;
use App\Services\System\BackupService;
use App\Services\System\DeploymentService;
use Core\Auth;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;
use Core\Session;

class DeploymentController extends Controller
{
    public function index(): Response
    {
        $service = new DeploymentService();

        return $this->view('superadmin.deployments.index', [
            'title'       => 'Despliegues',
            'active'      => 'deployments',
            'status'      => $service->systemStatus(),
            'deployments' => (new Deployment())->history(25),
            'backups'     => (new BackupService())->list(10),
            'changelog'   => $service->changelog(5),
            'updates'     => Session::getFlash('_update_check'),
            'result'      => Session::getFlash('_deploy_result'),
        ]);
    }

    public function show(string $id): Response
    {
        $deployment = (new Deployment())->find((int) $id);

        if ($deployment === null) {
            throw HttpException::notFound('Despliegue no encontrado');
        }

        return $this->view('superadmin.deployments.show', [
            'title'      => 'Despliegue #' . $deployment['id'],
            'active'     => 'deployments',
            'deployment' => $deployment,
        ]);
    }

    /** Ejecuta la actualización completa. */
    public function deploy(): Response
    {
        if (!$this->confirmSensitiveAction()) {
            return $this->back('/super-admin/despliegues');
        }

        $service = new DeploymentService();
        $result  = $service->deploy(Auth::id(), $this->request->boolean('force'));

        if ($this->request->expectsJson()) {
            return $result['ok']
                ? $this->success($result['message'], $result)
                : $this->fail($result['message'], ['steps' => $result['steps']], 200);
        }

        Session::flash('_deploy_result', $result);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirect('/super-admin/despliegues');
    }

    /** Rollback: requiere confirmación adicional explícita (spec §130). */
    public function rollback(string $id): Response
    {
        if (!$this->confirmSensitiveAction()) {
            return $this->back('/super-admin/despliegues');
        }

        if ((string) $this->request->input('confirm') !== 'RESTAURAR') {
            Session::flash('error', 'Para confirmar el rollback escribe RESTAURAR en el campo de confirmación.');

            return $this->back('/super-admin/despliegues');
        }

        $result = (new DeploymentService())->rollback((int) $id, Auth::id());

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirect('/super-admin/despliegues');
    }

    private function confirmSensitiveAction(): bool
    {
        if ($this->request->filled('confirm_password')) {
            if (Auth::confirmPassword((string) $this->request->input('confirm_password'))) {
                return true;
            }

            Session::flash('error', 'La contraseña ingresada no es correcta.');

            return false;
        }

        if (Auth::recentlyConfirmed(900)) {
            return true;
        }

        Session::flash('error', 'Por seguridad, confirma tu contraseña para ejecutar esta acción.');

        return false;
    }
}
