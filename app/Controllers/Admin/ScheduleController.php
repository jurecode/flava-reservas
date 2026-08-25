<?php
/**
 * Ruta: /app/Controllers/Admin/ScheduleController.php
 * Horarios semanales y bloqueos de agenda (spec §21, §22).
 */

namespace App\Controllers\Admin;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BlockedTime;
use App\Models\Branch;
use App\Services\ActivityLogger;
use App\Support\DateHelper;
use Core\Auth;
use Core\Controller;
use Core\Response;
use Core\Session;
use Core\Validator;

class ScheduleController extends Controller
{
    public function edit(string $id): Response
    {
        $barber = (new Barber())->findOrFail((int) $id);

        return $this->view('admin.schedules.edit', [
            'title'   => 'Horario de ' . $barber['display_name'],
            'active'  => 'barbers',
            'barber'  => $barber,
            'week'    => (new BarberSchedule())->weekFor((int) $id),
            'days'    => DateHelper::DAYS,
        ]);
    }

    public function update(string $id): Response
    {
        $barber = (new Barber())->findOrFail((int) $id);
        $input  = (array) $this->request->raw('schedule', []);
        $week   = [];
        $errors = [];

        foreach (range(1, 7) as $weekday) {
            $blocks = $input[$weekday] ?? [];

            foreach ((array) $blocks as $index => $block) {
                $start = trim((string) ($block['start_time'] ?? ''));
                $end   = trim((string) ($block['end_time'] ?? ''));

                if ($start === '' && $end === '') {
                    continue;
                }

                if ($start === '' || $end === '') {
                    $errors[] = DateHelper::DAYS[$weekday] . ': falta la hora de inicio o término.';
                    continue;
                }

                if ($start >= $end) {
                    $errors[] = DateHelper::DAYS[$weekday] . ': la hora de término debe ser posterior al inicio.';
                    continue;
                }

                $week[$weekday][] = ['start_time' => $start, 'end_time' => $end];
            }

            // Detecta bloques solapados dentro del mismo día.
            $day = $week[$weekday] ?? [];
            usort($day, static fn (array $a, array $b): int => strcmp($a['start_time'], $b['start_time']));

            for ($i = 1; $i < count($day); $i++) {
                if ($day[$i]['start_time'] < $day[$i - 1]['end_time']) {
                    $errors[] = DateHelper::DAYS[$weekday] . ': hay bloques que se solapan.';
                    break;
                }
            }

            if ($day !== []) {
                $week[$weekday] = $day;
            }
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', array_unique($errors)));

            return $this->back();
        }

        (new BarberSchedule())->replaceWeek((int) $id, $week);
        ActivityLogger::log('schedule.updated', 'barber', (int) $id, 'Horario de ' . $barber['display_name']);

        return $this->redirectWith('/admin/barberos/' . $id . '/horario', 'Horario actualizado.');
    }

    // -----------------------------------------------------------------
    //  Bloqueos
    // -----------------------------------------------------------------

    public function blocks(): Response
    {
        $from = (string) ($this->request->input('from') ?: today());
        $to   = (string) ($this->request->input('to') ?: DateHelper::make($from)->modify('+30 days')->format('Y-m-d'));

        return $this->view('admin.schedules.blocks', [
            'title'   => 'Bloqueos de agenda',
            'active'  => 'blocks',
            'blocks'  => (new BlockedTime())->forRange($from, $to, null, Branch::defaultId()),
            'barbers' => (new Barber())->activeList(),
            'types'   => BlockedTime::TYPES,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function storeBlock(): Response
    {
        $validator = new Validator($this->request->all(), [
            'start_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|time',
            'end_date'   => 'required|date_format:Y-m-d',
            'end_time'   => 'required|time',
            'type'       => 'required|in:' . implode(',', array_keys(BlockedTime::TYPES)),
            'reason'     => 'nullable|max:255',
            'barber_id'  => 'nullable|integer|exists:barbers,id',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($validator->errors());
        }

        $start = $this->request->input('start_date') . ' ' . substr((string) $this->request->input('start_time'), 0, 5) . ':00';
        $end   = $this->request->input('end_date') . ' ' . substr((string) $this->request->input('end_time'), 0, 5) . ':00';

        if ($start >= $end) {
            Session::flash('error', 'El término del bloqueo debe ser posterior a su inicio.');

            return $this->back();
        }

        $barberId = $this->request->integer('barber_id');
        $model    = new BlockedTime();

        // Avisa si el bloqueo pisa reservas activas: no las elimina en silencio.
        $conflicts = $model->conflictingBookings($barberId, $start, $end);

        if ($conflicts !== [] && !$this->request->boolean('force')) {
            Session::flash('error', sprintf(
                'Hay %d reserva(s) activa(s) en ese rango (%s). Reprográmalas primero o marca "bloquear de todos modos".',
                count($conflicts),
                implode(', ', array_column($conflicts, 'public_code'))
            ));

            return $this->back();
        }

        $id = $model->create([
            'branch_id'      => Branch::defaultId(),
            'barber_id'      => $barberId,
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'type'           => (string) $this->request->input('type'),
            'reason'         => $this->request->input('reason') ?: null,
            'created_by'     => Auth::id(),
        ]);

        ActivityLogger::log('blocked.created', 'blocked_time', $id, sprintf(
            'Bloqueo %s de %s a %s',
            BlockedTime::typeLabel((string) $this->request->input('type')),
            $start,
            $end
        ));

        return $this->respondOk('Bloqueo creado.');
    }

    public function deleteBlock(string $id): Response
    {
        $model = new BlockedTime();
        $block = $model->findOrFail((int) $id);

        $model->delete((int) $id);
        ActivityLogger::log('blocked.deleted', 'blocked_time', (int) $id, 'Eliminó un bloqueo de ' . $block['start_datetime']);

        return $this->respondOk('Bloqueo eliminado.');
    }

    private function respondOk(string $message): Response
    {
        if ($this->request->expectsJson()) {
            return $this->success($message);
        }

        Session::flash('success', $message);

        return $this->back('/admin/bloqueos');
    }
}
