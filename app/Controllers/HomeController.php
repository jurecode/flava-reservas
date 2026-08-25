<?php
/**
 * Ruta: /app/Controllers/HomeController.php
 * Sitio público de Flava Studio.
 */

namespace App\Controllers;

use App\Models\Barber;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\SettingService;
use Core\Controller;
use Core\Exceptions\HttpException;
use Core\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $services = (new Service())->bookable();

        return $this->view('site.home', [
            'title'       => setting('business_name', config('app.name')) . ' | Reserva tu hora online',
            'description' => 'Reserva tu hora en ' . setting('business_name', config('app.name'))
                . '. Elige tu servicio, barbero, fecha y horario disponible.',
            'business'    => SettingService::business(),
            'branch'      => (new Branch())->default(),
            'featured'    => array_values(array_filter($services, static fn (array $s): bool => (int) $s['is_featured'] === 1)),
            'services'    => $services,
            'barbers'     => (new Barber())->publicList(Branch::defaultId()),
        ]);
    }

    public function services(): Response
    {
        return $this->view('site.services', [
            'title'       => 'Servicios | ' . setting('business_name', config('app.name')),
            'description' => 'Cortes, barba y servicios premium. Conoce precios y duración de cada servicio.',
            'business'    => SettingService::business(),
            'categories'  => (new ServiceCategory())->withServices(),
            'services'    => (new Service())->bookable(),
        ]);
    }

    public function barbers(): Response
    {
        $barbers = (new Barber())->publicList(Branch::defaultId());
        $model   = new Barber();

        foreach ($barbers as $index => $barber) {
            $barbers[$index]['services'] = $model->servicesWithPricing((int) $barber['id']);
        }

        return $this->view('site.barbers', [
            'title'       => 'Barberos | ' . setting('business_name', config('app.name')),
            'description' => 'Conoce al equipo de ' . setting('business_name', config('app.name')) . ' y reserva con tu barbero de confianza.',
            'business'    => SettingService::business(),
            'barbers'     => $barbers,
        ]);
    }

    public function barber(string $slug): Response
    {
        $model  = new Barber();
        $barber = $model->findBySlug($slug);

        if ($barber === null || (int) $barber['status'] !== 1) {
            throw HttpException::notFound('No encontramos a ese barbero');
        }

        return $this->view('site.barber', [
            'title'       => $barber['display_name'] . ' | ' . setting('business_name', config('app.name')),
            'description' => trim((string) $barber['specialty']) ?: 'Reserva tu hora con ' . $barber['display_name'],
            'business'    => SettingService::business(),
            'barber'      => $barber,
            'services'    => $model->servicesWithPricing((int) $barber['id']),
        ]);
    }

    public function contact(): Response
    {
        return $this->view('site.contact', [
            'title'       => 'Contacto | ' . setting('business_name', config('app.name')),
            'description' => 'Dónde estamos, horarios y cómo contactarnos.',
            'business'    => SettingService::business(),
            'branch'      => (new Branch())->default(),
        ]);
    }
}
