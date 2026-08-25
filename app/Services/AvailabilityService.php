<?php
/**
 * Ruta: /app/Services/AvailabilityService.php
 *
 * MOTOR CENTRAL DE DISPONIBILIDAD (spec §24, §71).
 * Es la ÚNICA fuente de verdad sobre qué horarios están libres. Lo usan por
 * igual el booking público, recepción, administración y la futura API: si
 * hubiera dos cálculos distintos aparecerían inconsistencias.
 *
 * Considera:
 *   · horarios semanales del barbero (varios bloques por día)
 *   · duración real del servicio (no intervalos fijos de 30 min)
 *   · reservas existentes en estados que bloquean
 *   · bloqueos del barbero y de la sucursal (almuerzo, vacaciones, feriados)
 *   · hora actual y anticipación mínima configurable
 *   · colchón entre citas configurable
 *   · máxima anticipación permitida
 */

namespace App\Services;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BlockedTime;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Service;
use App\Support\DateHelper;

final class AvailabilityService
{
    public function __construct(
        private readonly Booking $bookings = new Booking(),
        private readonly BarberSchedule $schedules = new BarberSchedule(),
        private readonly BlockedTime $blocks = new BlockedTime(),
        private readonly Barber $barbers = new Barber(),
        private readonly Service $services = new Service(),
    ) {
    }

    // -----------------------------------------------------------------
    //  Parámetros de negocio (configurables desde el panel)
    // -----------------------------------------------------------------

    public function slotInterval(): int
    {
        return max(5, (int) setting('slot_interval', 15));
    }

    public function bufferMinutes(): int
    {
        return max(0, (int) setting('buffer_minutes', 0));
    }

    public function minAdvanceMinutes(): int
    {
        return max(0, (int) setting('min_advance_minutes', 60));
    }

    public function maxAdvanceDays(): int
    {
        return max(1, (int) setting('max_advance_days', 60));
    }

    // -----------------------------------------------------------------
    //  Cálculo de slots
    // -----------------------------------------------------------------

    /**
     * Horarios disponibles de un barbero para un servicio en una fecha.
     *
     * @param bool $internal true para recepción/admin: ignora la anticipación
     *                       mínima pensada para el cliente web (pero nunca
     *                       permite solaparse con otra reserva).
     * @return array<int,array{time:string,end:string,label:string}>
     */
    public function slotsFor(int $barberId, int $serviceId, string $date, bool $internal = false, ?int $excludeBookingId = null): array
    {
        $service = $this->services->find($serviceId);
        $barber  = $this->barbers->find($barberId);

        if ($service === null || $barber === null || (int) $barber['status'] !== 1) {
            return [];
        }

        if (!$this->dateIsBookable($date, $internal)) {
            return [];
        }

        $effective = $this->services->effectiveFor($service, $barberId);
        $duration  = (int) $effective['duration'];

        if ($duration <= 0) {
            return [];
        }

        $windows = $this->workingWindows($barberId, $date);

        if ($windows === []) {
            return [];
        }

        $busy      = $this->busyIntervals($barberId, $date, (int) $barber['branch_id'], $excludeBookingId);
        $interval  = $this->slotInterval();
        $buffer    = $this->bufferMinutes();
        $minMinute = $this->earliestBookableMinute($date, $internal);
        $slots     = [];

        foreach ($windows as $window) {
            // Alinea el primer intento al inicio del bloque de trabajo.
            for ($start = $window['start']; $start + $duration <= $window['end']; $start += $interval) {
                if ($start < $minMinute) {
                    continue;
                }

                $end = $start + $duration;

                if ($this->conflicts($start, $end, $busy, $buffer)) {
                    continue;
                }

                $time    = DateHelper::fromMinutes($start);
                $slots[] = [
                    'time'  => $time,
                    'end'   => DateHelper::fromMinutes($end),
                    'label' => $time,
                ];
            }
        }

        // Un barbero puede tener bloques solapados mal configurados: deduplicar.
        $unique = [];
        foreach ($slots as $slot) {
            $unique[$slot['time']] = $slot;
        }

        ksort($unique);

        return array_values($unique);
    }

    /**
     * Slots agregados de TODOS los barberos que realizan el servicio.
     * Alimenta la opción "Cualquier barbero disponible" (spec §23).
     *
     * @return array<int,array{time:string,end:string,label:string,barber_id:int,barber_name:string,options:array<int,int>}>
     */
    public function slotsForAnyBarber(int $serviceId, string $date, bool $internal = false): array
    {
        $result = [];

        foreach ($this->barbers->availableForService($serviceId, !$internal) as $barber) {
            foreach ($this->slotsFor((int) $barber['id'], $serviceId, $date, $internal) as $slot) {
                $time = $slot['time'];

                if (!isset($result[$time])) {
                    $result[$time] = $slot + [
                        'barber_id'   => (int) $barber['id'],
                        'barber_name' => $barber['display_name'],
                        'options'     => [],
                    ];
                }

                $result[$time]['options'][] = (int) $barber['id'];
            }
        }

        ksort($result);

        return array_values($result);
    }

    /**
     * Primer barbero disponible a una hora concreta (reparte la carga eligiendo
     * al que tenga menos reservas ese día).
     */
    public function firstAvailableBarber(int $serviceId, string $date, string $time, bool $internal = false): ?int
    {
        $candidates = [];

        foreach ($this->barbers->availableForService($serviceId, !$internal) as $barber) {
            $barberId = (int) $barber['id'];

            foreach ($this->slotsFor($barberId, $serviceId, $date, $internal) as $slot) {
                if ($slot['time'] === substr($time, 0, 5)) {
                    $candidates[$barberId] = count($this->bookings->occupiedSlots($barberId, $date));
                    break;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        asort($candidates);

        return (int) array_key_first($candidates);
    }

    /**
     * Fechas con al menos un horario libre (selector de fechas del booking).
     *
     * @param int|null $barberId null = cualquier barbero
     * @return array<int,array{date:string,label:string,available:bool,slots:int}>
     */
    public function availableDates(int $serviceId, ?int $barberId, int $days = 14, bool $internal = false): array
    {
        $result = [];
        $cursor = DateHelper::make();
        $limit  = min($days, $this->maxAdvanceDays());

        for ($i = 0; $i < $limit; $i++) {
            $date  = $cursor->modify("+{$i} days")->format('Y-m-d');
            $slots = $barberId !== null
                ? $this->slotsFor($barberId, $serviceId, $date, $internal)
                : $this->slotsForAnyBarber($serviceId, $date, $internal);

            $result[] = [
                'date'      => $date,
                'label'     => DateHelper::friendly($date),
                'weekday'   => DateHelper::DAYS_SHORT[DateHelper::weekday($date)],
                'day'       => (int) DateHelper::make($date)->format('j'),
                'month'     => DateHelper::MONTHS_SHORT[(int) DateHelper::make($date)->format('n')],
                'available' => $slots !== [],
                'slots'     => count($slots),
            ];
        }

        return $result;
    }

    // -----------------------------------------------------------------
    //  Validación (se ejecuta SIEMPRE al confirmar, spec §24)
    // -----------------------------------------------------------------

    /**
     * ¿Es válido reservar ese barbero/servicio/fecha/hora?
     * No confía en lo que mostró el frontend.
     *
     * @param bool $forUpdate true dentro de la transacción de creación: bloquea
     *                        las filas del rango para impedir carreras (spec §25).
     * @return array{ok:bool,reason:?string,end_time:?string,duration:?int,price:?float}
     */
    public function validateSlot(
        int $barberId,
        int $serviceId,
        string $date,
        string $time,
        bool $internal = false,
        ?int $excludeBookingId = null,
        bool $forUpdate = false
    ): array {
        $service = $this->services->find($serviceId);
        $barber  = $this->barbers->find($barberId);

        if ($service === null || (int) $service['status'] !== 1) {
            return $this->reject('El servicio seleccionado no está disponible.');
        }
        if ($barber === null || (int) $barber['status'] !== 1) {
            return $this->reject('El barbero seleccionado no está disponible.');
        }
        if (!$this->barberDoesService($barberId, $serviceId)) {
            return $this->reject('Ese barbero no realiza el servicio seleccionado.');
        }
        if (!$this->dateIsBookable($date, $internal)) {
            return $this->reject('La fecha seleccionada no está disponible para reservas.');
        }

        $effective = $this->services->effectiveFor($service, $barberId);
        $duration  = (int) $effective['duration'];
        $start     = DateHelper::toMinutes(substr($time, 0, 5));
        $end       = $start + $duration;

        if ($start < $this->earliestBookableMinute($date, $internal)) {
            return $this->reject(
                $internal
                    ? 'Esa hora ya pasó.'
                    : 'Las reservas online requieren al menos ' . $this->minAdvanceMinutes() . ' minutos de anticipación.'
            );
        }

        // 1) ¿Está dentro del horario de trabajo del barbero?
        $insideWindow = false;
        foreach ($this->workingWindows($barberId, $date) as $window) {
            if ($start >= $window['start'] && $end <= $window['end']) {
                $insideWindow = true;
                break;
            }
        }

        if (!$insideWindow) {
            return $this->reject('El barbero no atiende en ese horario. Selecciona otro disponible.');
        }

        // 2) ¿Choca con reservas o bloqueos? (con FOR UPDATE si estamos confirmando)
        $busy = $this->busyIntervals($barberId, $date, (int) $barber['branch_id'], $excludeBookingId, $forUpdate);

        if ($this->conflicts($start, $end, $busy, $this->bufferMinutes())) {
            return $this->reject('Este horario acaba de ser reservado. Selecciona otro disponible.');
        }

        return [
            'ok'       => true,
            'reason'   => null,
            'end_time' => DateHelper::fromMinutes($end),
            'duration' => $duration,
            'price'    => (float) $effective['price'],
        ];
    }

    public function barberDoesService(int $barberId, int $serviceId): bool
    {
        return (int) \Core\Database::instance()->scalar(
            'SELECT COUNT(*) FROM barber_services WHERE barber_id = :b AND service_id = :s',
            ['b' => $barberId, 's' => $serviceId]
        ) > 0;
    }

    // -----------------------------------------------------------------
    //  Internos
    // -----------------------------------------------------------------

    /**
     * Bloques de trabajo del barbero ese día, en minutos desde medianoche.
     * @return array<int,array{start:int,end:int}>
     */
    public function workingWindows(int $barberId, string $date): array
    {
        $windows = [];

        foreach ($this->schedules->forWeekday($barberId, DateHelper::weekday($date), $date) as $block) {
            $start = DateHelper::toMinutes(substr((string) $block['start_time'], 0, 5));
            $end   = DateHelper::toMinutes(substr((string) $block['end_time'], 0, 5));

            if ($end > $start) {
                $windows[] = ['start' => $start, 'end' => $end];
            }
        }

        usort($windows, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $windows;
    }

    /**
     * Intervalos ocupados (reservas + bloqueos) en minutos desde medianoche.
     * @return array<int,array{start:int,end:int,type:string}>
     */
    public function busyIntervals(int $barberId, string $date, int $branchId, ?int $excludeBookingId = null, bool $forUpdate = false): array
    {
        $intervals = [];

        foreach ($this->bookings->occupiedSlots($barberId, $date, $forUpdate, $excludeBookingId) as $booking) {
            $intervals[] = [
                'start' => DateHelper::toMinutes(substr((string) $booking['start_time'], 0, 5)),
                'end'   => DateHelper::toMinutes(substr((string) $booking['end_time'], 0, 5)),
                'type'  => 'booking',
            ];
        }

        foreach ($this->blocks->forBarberOnDate($barberId, $date, $branchId) as $block) {
            // Un bloqueo puede abarcar varios días: recortarlo al día consultado.
            $start = substr((string) $block['start_datetime'], 0, 10) < $date
                ? 0
                : DateHelper::toMinutes(substr((string) $block['start_datetime'], 11, 5));

            $end = substr((string) $block['end_datetime'], 0, 10) > $date
                ? 24 * 60
                : DateHelper::toMinutes(substr((string) $block['end_datetime'], 11, 5));

            if ($end > $start) {
                $intervals[] = ['start' => $start, 'end' => $end, 'type' => 'block'];
            }
        }

        return $intervals;
    }

    /**
     * ¿El rango [start,end) choca con algún intervalo ocupado?
     * El colchón se aplica sólo entre reservas, no contra bloqueos rígidos.
     */
    private function conflicts(int $start, int $end, array $busy, int $buffer): bool
    {
        foreach ($busy as $interval) {
            $pad       = $interval['type'] === 'booking' ? $buffer : 0;
            $busyStart = $interval['start'] - $pad;
            $busyEnd   = $interval['end'] + $pad;

            if (DateHelper::overlaps($start, $end, $busyStart, $busyEnd)) {
                return true;
            }
        }

        return false;
    }

    /** Primer minuto reservable del día considerando "ahora" y la anticipación. */
    private function earliestBookableMinute(string $date, bool $internal): int
    {
        $now = DateHelper::make();

        if ($date > $now->format('Y-m-d')) {
            return 0;
        }
        if ($date < $now->format('Y-m-d')) {
            return 24 * 60; // día pasado: nada disponible
        }

        $currentMinute = (int) $now->format('H') * 60 + (int) $now->format('i');
        $advance       = $internal ? 0 : $this->minAdvanceMinutes();
        $earliest      = $currentMinute + $advance;

        // Redondea hacia arriba al siguiente múltiplo del intervalo.
        $interval = $this->slotInterval();

        return (int) (ceil($earliest / $interval) * $interval);
    }

    /** ¿La fecha está dentro de la ventana permitida? */
    public function dateIsBookable(string $date, bool $internal = false): bool
    {
        $today = DateHelper::make()->format('Y-m-d');

        if ($date < $today) {
            return false;
        }

        if ($internal) {
            return true;
        }

        $maxDate = DateHelper::make()->modify('+' . $this->maxAdvanceDays() . ' days')->format('Y-m-d');

        return $date <= $maxDate;
    }

    /** @return array{ok:bool,reason:string,end_time:null,duration:null,price:null} */
    private function reject(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason, 'end_time' => null, 'duration' => null, 'price' => null];
    }

    // -----------------------------------------------------------------
    //  Utilidades para la agenda / dashboards
    // -----------------------------------------------------------------

    /**
     * Línea de tiempo del día de un barbero: reservas + huecos libres.
     * Es lo que ve el barbero en su panel (spec §17).
     *
     * @return array<int,array{type:string,start:string,end:string,booking:?array}>
     */
    public function dayTimeline(int $barberId, string $date, int $freeSlotMinutes = 30): array
    {
        $barber = $this->barbers->find($barberId);

        if ($barber === null) {
            return [];
        }

        $windows  = $this->workingWindows($barberId, $date);
        $bookings = $this->bookings->agendaFor($barberId, $date);
        $blocks   = $this->blocks->forBarberOnDate($barberId, $date, (int) $barber['branch_id']);
        $timeline = [];

        foreach ($bookings as $booking) {
            if (in_array($booking['status'], ['cancelled'], true)) {
                continue;
            }

            $timeline[] = [
                'type'    => 'booking',
                'start'   => DateHelper::toMinutes(substr((string) $booking['start_time'], 0, 5)),
                'end'     => DateHelper::toMinutes(substr((string) $booking['end_time'], 0, 5)),
                'booking' => $booking,
                'block'   => null,
            ];
        }

        foreach ($blocks as $block) {
            $start = substr((string) $block['start_datetime'], 0, 10) < $date ? 0
                : DateHelper::toMinutes(substr((string) $block['start_datetime'], 11, 5));
            $end   = substr((string) $block['end_datetime'], 0, 10) > $date ? 24 * 60
                : DateHelper::toMinutes(substr((string) $block['end_datetime'], 11, 5));

            $timeline[] = [
                'type'    => 'block',
                'start'   => $start,
                'end'     => $end,
                'booking' => null,
                'block'   => $block,
            ];
        }

        // Huecos libres dentro de la jornada
        foreach ($windows as $window) {
            $cursor = $window['start'];

            $occupied = array_values(array_filter(
                $timeline,
                static fn (array $item): bool => $item['end'] > $window['start'] && $item['start'] < $window['end']
            ));

            usort($occupied, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

            foreach ($occupied as $item) {
                if ($item['start'] > $cursor) {
                    $timeline[] = [
                        'type'    => 'free',
                        'start'   => $cursor,
                        'end'     => min($item['start'], $window['end']),
                        'booking' => null,
                        'block'   => null,
                    ];
                }
                $cursor = max($cursor, $item['end']);
            }

            if ($cursor < $window['end']) {
                $timeline[] = [
                    'type'    => 'free',
                    'start'   => $cursor,
                    'end'     => $window['end'],
                    'booking' => null,
                    'block'   => null,
                ];
            }
        }

        usort($timeline, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return array_map(static function (array $item): array {
            $item['start_label'] = DateHelper::fromMinutes($item['start']);
            $item['end_label']   = DateHelper::fromMinutes($item['end']);
            $item['minutes']     = $item['end'] - $item['start'];

            return $item;
        }, $timeline);
    }

    /** % de ocupación de un barbero en una fecha (dashboard). */
    public function occupancyRate(int $barberId, string $date): float
    {
        $capacity = 0;
        foreach ($this->workingWindows($barberId, $date) as $window) {
            $capacity += $window['end'] - $window['start'];
        }

        if ($capacity === 0) {
            return 0.0;
        }

        $booked = 0;
        foreach ($this->bookings->occupiedSlots($barberId, $date) as $booking) {
            $booked += (int) $booking['duration_minutes'];
        }

        return round(min(100, ($booked / $capacity) * 100), 1);
    }

    /** Ocupación global de la sucursal para una fecha. */
    public function branchOccupancy(string $date, ?int $branchId = null): float
    {
        $branchId ??= Branch::defaultId();
        $barbers    = $this->schedules->barbersWorkingOn($date, $branchId);

        if ($barbers === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($barbers as $barber) {
            $total += $this->occupancyRate((int) $barber['id'], $date);
        }

        return round($total / count($barbers), 1);
    }
}
