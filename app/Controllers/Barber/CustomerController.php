<?php
/**
 * Ruta: /app/Controllers/Barber/CustomerController.php
 *
 * El barbero ve información pertinente del cliente (spec §18): nombre,
 * teléfono, historial y notas técnicas. NO ve RUT, email, montos ni datos
 * administrativos: eso lo filtra CustomerService::profileForBarber().
 */

namespace App\Controllers\Barber;

use App\Controllers\Barber\Concerns\ResolvesBarber;
use App\Models\Booking;
use App\Services\CustomerService;
use App\Support\DateHelper;
use Core\Controller;
use Core\Database;
use Core\Response;

class CustomerController extends Controller
{
    use ResolvesBarber;

    /** Clientes que este barbero ha atendido. */
    public function index(): Response
    {
        $barber = $this->currentBarber();
        $search = trim((string) $this->request->input('q'));

        $sql = "SELECT c.id, c.first_name, c.last_name, c.phone,
                       COUNT(bk.id) AS visits,
                       MAX(bk.booking_date) AS last_visit
                FROM customers c
                INNER JOIN bookings bk ON bk.customer_id = c.id
                WHERE bk.barber_id = :barber AND bk.status = 'completed'";

        $bindings = ['barber' => (int) $barber['id']];

        if ($search !== '') {
            $sql               .= " AND (CONCAT(c.first_name,' ',c.last_name) LIKE :search OR c.phone LIKE :phone)";
            $bindings['search'] = '%' . $search . '%';
            $bindings['phone']  = '%' . $search . '%';
        }

        $customers = Database::instance()->select(
            $sql . ' GROUP BY c.id ORDER BY last_visit DESC LIMIT 60',
            $bindings
        );

        return $this->view('barber.customers.index', [
            'title'     => 'Mis clientes',
            'active'    => 'customers',
            'barber'    => $barber,
            'customers' => $customers,
            'search'    => $search,
        ]);
    }

    public function show(string $id): Response
    {
        $barber  = $this->currentBarber();
        $profile = (new CustomerService())->profileForBarber((int) $id);

        // Debe existir al menos una atención con este barbero.
        $attended = (int) Database::instance()->scalar(
            'SELECT COUNT(*) FROM bookings WHERE customer_id = :c AND barber_id = :b',
            ['c' => (int) $id, 'b' => (int) $barber['id']]
        );

        $this->authorize($attended > 0, 'Sólo puedes ver clientes que has atendido.');

        $next = (new Booking())->upcoming(1, (int) $barber['id']);

        return $this->view('barber.customers.show', [
            'title'    => trim($profile['customer']['first_name'] . ' ' . $profile['customer']['last_name']),
            'active'   => 'customers',
            'barber'   => $barber,
            'customer' => $profile['customer'],
            'history'  => $profile['history'],
            'notes'    => $profile['service_notes'],
            'lastVisit' => $profile['customer']['last_visit_at']
                ? DateHelper::longEs((string) $profile['customer']['last_visit_at'], true, false)
                : null,
        ]);
    }
}
