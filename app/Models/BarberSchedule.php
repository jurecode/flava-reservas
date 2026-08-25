<?php
/**
 * Ruta: /app/Models/BarberSchedule.php
 * Horario semanal del barbero. Admite varios bloques por día (spec §21).
 */

namespace App\Models;

use App\Support\DateHelper;
use Core\Model;

class BarberSchedule extends Model
{
    protected string $table = 'barber_schedules';

    protected array $fillable = [
        'barber_id', 'weekday', 'start_time', 'end_time', 'valid_from', 'valid_to', 'status',
    ];

    /** Bloques activos de un barbero para un día ISO (1..7) y una fecha concreta. */
    public function forWeekday(int $barberId, int $weekday, ?string $date = null): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE barber_id = :b AND weekday = :d AND status = 1";

        $bindings = ['b' => $barberId, 'd' => $weekday];

        if ($date !== null) {
            $sql            .= ' AND (valid_from IS NULL OR valid_from <= :date1)
                                AND (valid_to   IS NULL OR valid_to   >= :date2)';
            $bindings['date1'] = $date;
            $bindings['date2'] = $date;
        }

        return $this->db()->select($sql . ' ORDER BY start_time', $bindings);
    }

    /** @return array<int,array<int,array>> Horario completo indexado por día. */
    public function weekFor(int $barberId): array
    {
        $week = array_fill_keys(range(1, 7), []);

        foreach ($this->where(['barber_id' => $barberId, 'status' => 1], 'weekday, start_time') as $block) {
            $week[(int) $block['weekday']][] = $block;
        }

        return $week;
    }

    /** ¿Trabaja el barbero ese día? */
    public function worksOn(int $barberId, string $date): bool
    {
        return $this->forWeekday($barberId, DateHelper::weekday($date), $date) !== [];
    }

    /**
     * Reemplaza el horario completo de un barbero.
     * @param array<int,array<int,array{start_time:string,end_time:string}>> $week
     */
    public function replaceWeek(int $barberId, array $week): void
    {
        $this->db()->transaction(function () use ($barberId, $week): void {
            $this->db()->delete($this->table, 'barber_id = :b', ['b' => $barberId]);

            foreach ($week as $weekday => $blocks) {
                foreach ($blocks as $block) {
                    $start = substr((string) ($block['start_time'] ?? ''), 0, 5);
                    $end   = substr((string) ($block['end_time'] ?? ''), 0, 5);

                    if ($start === '' || $end === '' || $start >= $end) {
                        continue;
                    }

                    $this->create([
                        'barber_id'  => $barberId,
                        'weekday'    => (int) $weekday,
                        'start_time' => $start . ':00',
                        'end_time'   => $end . ':00',
                        'status'     => 1,
                    ]);
                }
            }
        });
    }

    /** Barberos que trabajan un día determinado (para la agenda). */
    public function barbersWorkingOn(string $date, ?int $branchId = null): array
    {
        $sql = "SELECT DISTINCT b.*
                FROM barbers b
                INNER JOIN {$this->table} s ON s.barber_id = b.id AND s.status = 1
                WHERE b.status = 1
                  AND s.weekday = :weekday
                  AND (s.valid_from IS NULL OR s.valid_from <= :date1)
                  AND (s.valid_to   IS NULL OR s.valid_to   >= :date2)";

        $bindings = [
            'weekday' => DateHelper::weekday($date),
            'date1'   => $date,
            'date2'   => $date,
        ];

        if ($branchId !== null) {
            $sql               .= ' AND b.branch_id = :branch';
            $bindings['branch'] = $branchId;
        }

        return $this->db()->select($sql . ' ORDER BY b.sort_order, b.display_name', $bindings);
    }
}
