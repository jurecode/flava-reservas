<?php
/**
 * Ruta: /app/Controllers/Admin/ServiceController.php
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ActivityLogger;
use App\Services\UploadService;
use App\Support\Money;
use App\Support\Str;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class ServiceController extends Controller
{
    public function index(): Response
    {
        $filters = $this->request->only(['search', 'category_id', 'status']);

        return $this->view('admin.services.index', [
            'title'      => 'Servicios',
            'active'     => 'services',
            'services'   => (new Service())->listWithCategory($filters),
            'categories' => (new ServiceCategory())->active(),
            'filters'    => $filters,
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin.services.form', [
            'title'      => 'Nuevo servicio',
            'active'     => 'services',
            'service'    => null,
            'categories' => (new ServiceCategory())->active(),
            'barbers'    => (new Barber())->activeList(),
            'assigned'   => [],
        ]);
    }

    public function edit(string $id): Response
    {
        $service = (new Service())->findOrFail((int) $id);

        return $this->view('admin.services.form', [
            'title'      => 'Editar servicio',
            'active'     => 'services',
            'service'    => $service,
            'categories' => (new ServiceCategory())->active(),
            'barbers'    => (new Barber())->activeList(),
            'assigned'   => array_map('intval', array_column((new Service())->barbers((int) $id), 'id')),
        ]);
    }

    public function store(): Response
    {
        $validator = new Validator($this->request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data          = $this->prepare($validator->validated());
        $data['slug']  = Str::uniqueSlug($data['name'], 'services');

        try {
            $data['image'] = $this->handleImage(null, $data['slug']);
        } catch (\RuntimeException $e) {
            return $this->backWithErrors(['image' => [$e->getMessage()]]);
        }

        $id = (new Service())->create($data);

        $this->syncBarbers($id);
        ActivityLogger::log('service.created', 'service', $id, 'Servicio ' . $data['name']);

        return $this->redirectWith('/admin/servicios', 'Servicio creado correctamente.');
    }

    public function update(string $id): Response
    {
        $model  = new Service();
        $before = $model->findOrFail((int) $id);

        $validator = new Validator($this->request->all(), $this->rules());

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $data = $this->prepare($validator->validated());

        if ($data['name'] !== $before['name']) {
            $data['slug'] = Str::uniqueSlug($data['name'], 'services', 'slug', (int) $id);
        }

        try {
            $data['image'] = $this->handleImage($before['image'], $data['slug'] ?? $before['slug']);
        } catch (\RuntimeException $e) {
            return $this->backWithErrors(['image' => [$e->getMessage()]]);
        }

        $model->update((int) $id, $data);
        $this->syncBarbers((int) $id);

        $after = $model->find((int) $id) ?? [];
        ActivityLogger::logChanges('service.updated', 'service', (int) $id, $before, $after, 'Servicio ' . $before['name']);

        // Cambio de precio: se destaca en la auditoría (spec §60)
        if ((float) $before['price'] !== (float) $data['price']) {
            ActivityLogger::log(
                'service.price_changed',
                'service',
                (int) $id,
                sprintf('Precio de %s: %s → %s', $before['name'], Money::format($before['price']), Money::format($data['price']))
            );
        }

        return $this->redirectWith('/admin/servicios', 'Servicio actualizado.');
    }

    public function toggleStatus(string $id): Response
    {
        $model   = new Service();
        $service = $model->findOrFail((int) $id);
        $status  = (int) $service['status'] === 1 ? 0 : 1;

        $model->update((int) $id, ['status' => $status]);
        ActivityLogger::log('service.updated', 'service', (int) $id, ($status ? 'Activó' : 'Desactivó') . ' el servicio ' . $service['name']);

        Session::flash('success', $status ? 'Servicio activado.' : 'Servicio desactivado.');

        return $this->back('/admin/servicios');
    }

    private function rules(): array
    {
        return [
            'name'             => 'required|min:2|max:120',
            'category_id'      => 'nullable|integer|exists:service_categories,id',
            'description'      => 'nullable|max:1000',
            'price'            => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'buffer_minutes'   => 'nullable|integer|min:0|max:120',
            'sort_order'       => 'nullable|integer|min:0',
            'color'            => 'nullable|max:9',
        ];
    }

    private function prepare(array $data): array
    {
        $data['price']            = Money::parse($data['price']);
        $data['duration_minutes'] = (int) $data['duration_minutes'];
        $data['buffer_minutes']   = (int) ($data['buffer_minutes'] ?? 0);
        $data['sort_order']       = (int) ($data['sort_order'] ?? 0);
        $data['is_featured']      = $this->request->boolean('is_featured') ? 1 : 0;
        $data['online_bookable']  = $this->request->boolean('online_bookable') ? 1 : 0;
        $data['status']           = $this->request->boolean('status') ? 1 : 0;
        $data['color']            = $data['color'] ?: '#FFC400';

        return $data;
    }

    /**
     * Resuelve la imagen del servicio: sube la nueva, conserva la actual o la
     * quita si el usuario marcó la casilla.
     */
    private function handleImage(?string $current, string $slug): ?string
    {
        $uploads = new UploadService();

        if ($this->request->boolean('image_remove')) {
            $uploads->delete($current);

            return null;
        }

        return $uploads->replace($this->request->file('image'), $current, 'services', $slug);
    }

    /** Sincroniza qué barberos realizan el servicio (spec §19). */
    private function syncBarbers(int $serviceId): void
    {
        $barberIds = array_map('intval', (array) $this->request->raw('barbers', []));
        $db        = \Core\Database::instance();

        $db->transaction(function () use ($db, $serviceId, $barberIds): void {
            $db->delete('barber_services', 'service_id = :s', ['s' => $serviceId]);

            foreach (array_unique($barberIds) as $barberId) {
                if ($barberId > 0) {
                    $db->insert('barber_services', ['barber_id' => $barberId, 'service_id' => $serviceId]);
                }
            }
        });
    }
}
