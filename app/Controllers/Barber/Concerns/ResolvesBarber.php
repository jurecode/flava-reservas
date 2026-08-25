<?php
/**
 * Ruta: /app/Controllers/Barber/Concerns/ResolvesBarber.php
 * Resuelve el barbero de la sesión. Compartido por los controladores del panel
 * para no duplicar la comprobación de permisos.
 */

namespace App\Controllers\Barber\Concerns;

use App\Models\Barber;
use App\Support\Role;
use Core\Auth;
use Core\Exceptions\HttpException;

trait ResolvesBarber
{
    /**
     * Barbero asociado a la sesión.
     *
     * Un BARBER siempre ve su propia ficha. Un ADMIN/SUPER_ADMIN puede inspeccionar
     * la de cualquiera con ?barber_id=; si no indica ninguna y no tiene ficha
     * propia, se muestra la del primer barbero activo para que el panel sea
     * navegable en vez de un callejón sin salida.
     */
    protected function currentBarber(): array
    {
        $model = new Barber();

        if (Auth::hasRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            $requested = $this->request->integer('barber_id');

            if ($requested !== null) {
                return $model->findOrFail($requested);
            }
        }

        $barberId = Auth::barberId();

        if ($barberId !== null) {
            return $model->findOrFail($barberId);
        }

        if (Auth::hasRole(Role::ADMIN, Role::SUPER_ADMIN)) {
            $first = $model->activeList()[0] ?? null;

            if ($first !== null) {
                return $first;
            }

            throw HttpException::notFound('Todavía no hay barberos creados. Crea el primero desde Administración.');
        }

        throw HttpException::forbidden(
            'Tu usuario no está vinculado a una ficha de barbero. Pídele a administración que la asocie.'
        );
    }
}
