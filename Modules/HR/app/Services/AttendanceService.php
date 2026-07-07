<?php

namespace Modules\HR\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;

class AttendanceService
{
    /**
     * Attendance history with optional filters (per employee, date range).
     *
     * @param  array{employee_id?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function list(string $businessId, array $filters = []): Collection
    {
        $query = Attendance::with('employee:id,name,position')
            ->where('business_id', $businessId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        return $query->orderByDesc('date')->orderByDesc('clock_in')->limit(500)->get();
    }

    /**
     * Clock in for today. Idempotent per (employee, date) via the unique index:
     * clocking in twice returns the existing row unchanged.
     */
    public function clockIn(Employee $employee, ?string $note = null): Attendance
    {
        return DB::transaction(function () use ($employee, $note) {
            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort_if($existing->clock_in !== null, 422, 'Sudah absen masuk hari ini.');

                $existing->update(['clock_in' => now(), 'status' => 'present', 'note' => $note]);

                return $existing->fresh();
            }

            return Attendance::create([
                'business_id' => $employee->business_id,
                'employee_id' => $employee->id,
                'date'        => now()->toDateString(),
                'clock_in'    => now(),
                'status'      => 'present',
                'note'        => $note,
            ]);
        });
    }

    /**
     * Clock out for today — requires an open clock-in.
     */
    public function clockOut(Employee $employee, ?string $note = null): Attendance
    {
        return DB::transaction(function () use ($employee, $note) {
            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            abort_if(! $attendance || $attendance->clock_in === null, 422, 'Belum absen masuk hari ini.');
            abort_if($attendance->clock_out !== null, 422, 'Sudah absen pulang hari ini.');

            $attendance->update([
                'clock_out' => now(),
                'note'      => $note ?? $attendance->note,
            ]);

            return $attendance->fresh();
        });
    }

    /**
     * Today's attendance row for an employee (null when not clocked in yet).
     */
    public function today(Employee $employee): ?Attendance
    {
        return Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();
    }
}
