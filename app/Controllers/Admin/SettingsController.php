<?php
/**
 * Ruta: /app/Controllers/Admin/SettingsController.php
 * Configuración editable desde el panel (spec §7).
 */

namespace App\Controllers\Admin;

use App\Models\Branch;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\SettingService;
use App\Support\PaymentMethod;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;

class SettingsController extends Controller
{
    /** Grupos editables desde este panel. El grupo `github` es del SUPER_ADMIN. */
    private const GROUPS = ['general', 'booking', 'payment', 'notify'];

    public function index(): Response
    {
        $model    = new Setting();
        $settings = [];

        foreach (self::GROUPS as $group) {
            $settings[$group] = $model->byGroup($group);
        }

        return $this->view('admin.settings.index', [
            'title'    => 'Configuración',
            'active'   => 'settings',
            'settings' => $settings,
            'business' => SettingService::business(),
            'branch'   => (new Branch())->default(),
            'methods'  => PaymentMethod::all(),
            'tab'      => (string) ($this->request->input('tab') ?: 'general'),
        ]);
    }

    public function update(): Response
    {
        $group = (string) $this->request->input('group', 'general');

        if (!in_array($group, self::GROUPS, true)) {
            Session::flash('error', 'Grupo de configuración no válido.');

            return $this->back('/admin/configuracion');
        }

        $values = (array) $this->request->raw('settings', []);
        $model  = new Setting();
        $before = [];
        $after  = [];

        // Sólo se aceptan claves que ya existen en la tabla y pertenecen al grupo.
        $allowed = array_column($model->byGroup($group), 'key_name');

        foreach ($values as $key => $value) {
            if (!in_array((string) $key, $allowed, true)) {
                continue;
            }

            $row = $model->findByKey((string) $key);

            if ($row === null || $row['type'] === 'secret') {
                continue;
            }

            if (!$this->isValid($row, $value)) {
                Session::flash('error', 'Valor inválido en «' . ($row['label'] ?: $key) . '».');

                return $this->back();
            }

            $before[$key] = $row['value'];
            $after[$key]  = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;

            $model->put((string) $key, $value, $row['type'], Auth::id(), $group);
        }

        // Los checkboxes no enviados equivalen a 0.
        foreach ($model->byGroup($group) as $row) {
            if ($row['type'] === 'boolean' && !array_key_exists($row['key_name'], $values)) {
                $model->put($row['key_name'], 0, 'boolean', Auth::id(), $group);
            }
        }

        SettingService::flush();

        if ($after !== []) {
            ActivityLogger::log('settings.updated', 'settings', null, 'Configuración: ' . $group, $before, $after);
        }

        return $this->redirectWith('/admin/configuracion?tab=' . $group, 'Configuración guardada.');
    }

    private function isValid(array $row, mixed $value): bool
    {
        return match ($row['type']) {
            'integer' => is_numeric($value) && (int) $value >= 0,
            'boolean' => in_array((string) $value, ['0', '1', 'on', 'true', 'false'], true),
            'json'    => is_array($value) || json_decode((string) $value, true) !== null,
            default   => is_string($value) || is_numeric($value),
        };
    }
}
