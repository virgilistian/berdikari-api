<?php

namespace Modules\HR\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Services\NotificationService;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveQuota;
use Modules\HR\Models\LeaveRequest;

class LeaveService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * List leave requests with optional filters.
     *
     * @param  array{employee_id?: ?string, status?: ?string}  $filters
     */
    public function list(string $businessId, array $filters = []): Collection
    {
        $query = LeaveRequest::with(['employee:id,name,position', 'approver:id,name'])
            ->where('business_id', $businessId);

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->limit(200)->get();
    }

    /**
     * Submit a leave request (always starts as pending — approval is a
     * separate, permission-gated step).
     * Validates quota for annual leave before submitting.
     *
     * @param  array{type: string, start_date: string, end_date: string, reason?: ?string}  $data
     */
    public function submit(Employee $employee, array $data): LeaveRequest
    {
        $overlapping = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        abort_if($overlapping, 422, 'Sudah ada pengajuan cuti pada rentang tanggal tersebut.');

        // Quota validation for annual leave
        if ($data['type'] === 'annual') {
            $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
            $year = Carbon::parse($data['start_date'])->year;
            $quota = $this->getOrCreateQuota($employee, $year, 'annual');
            $remaining = $quota->quota_days + $quota->carryover_days - $quota->used_days - $quota->pending_days;
            abort_if($days > $remaining, 422, "Kuota cuti tahunan tidak mencukupi. Tersisa {$remaining} hari.");
        }

        return DB::transaction(function () use ($employee, $data) {
            $leave = LeaveRequest::create([
                'business_id' => $employee->business_id,
                'employee_id' => $employee->id,
                'type'        => $data['type'],
                'start_date'  => $data['start_date'],
                'end_date'    => $data['end_date'],
                'reason'      => $data['reason'] ?? null,
                'status'      => 'pending',
            ]);

            // Increment pending_days in quota for annual type
            if ($data['type'] === 'annual') {
                $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
                $year = Carbon::parse($data['start_date'])->year;
                $this->getOrCreateQuota($employee, $year, 'annual')
                    ->increment('pending_days', $days);
            }

            // Notify managers/supervisors
            $this->notifications->broadcastToRole($employee->business_id, 'manager', [
                'type'  => 'leave_submitted',
                'title' => 'Pengajuan Cuti Baru',
                'body'  => "{$employee->name} mengajukan cuti dari {$data['start_date']} hingga {$data['end_date']}.",
                'meta'  => ['leave_id' => $leave->id, 'employee_id' => $employee->id],
            ]);
            $this->notifications->broadcastToRole($employee->business_id, 'supervisor', [
                'type'  => 'leave_submitted',
                'title' => 'Pengajuan Cuti Baru',
                'body'  => "{$employee->name} mengajukan cuti dari {$data['start_date']} hingga {$data['end_date']}.",
                'meta'  => ['leave_id' => $leave->id, 'employee_id' => $employee->id],
            ]);

            return $leave;
        });
    }

    /**
     * Approve or reject a pending request. An approver may never decide
     * their own request (least-privilege / no self-approval).
     */
    public function decide(LeaveRequest $leave, string $deciderUserId, string $decision, ?string $note = null): LeaveRequest
    {
        abort_if($leave->status !== 'pending', 422, 'Pengajuan ini sudah diproses.');
        abort_if(
            $leave->employee?->user_id !== null && $leave->employee->user_id === $deciderUserId,
            422,
            'Tidak dapat menyetujui pengajuan cuti sendiri.',
        );

        return DB::transaction(function () use ($leave, $deciderUserId, $decision, $note) {
            $leave->update([
                'status'        => $decision,
                'approved_by'   => $deciderUserId,
                'decided_at'    => now(),
                'decision_note' => $note,
            ]);

            // Update quota usage for annual type
            if ($leave->type === 'annual' && $leave->employee) {
                $days = Carbon::parse($leave->start_date)->diffInDays(Carbon::parse($leave->end_date)) + 1;
                $year = Carbon::parse($leave->start_date)->year;
                $quota = $this->getOrCreateQuota($leave->employee, $year, 'annual');

                if ($decision === 'approved') {
                    $quota->decrement('pending_days', min($days, $quota->pending_days));
                    $quota->increment('used_days', $days);
                } elseif ($decision === 'rejected') {
                    $quota->decrement('pending_days', min($days, $quota->pending_days));
                }
            }

            // Notify the employee (via their linked user account)
            $employee = $leave->employee;
            if ($employee?->user_id) {
                $notifTitle = $decision === 'approved' ? 'Cuti Disetujui' : 'Cuti Ditolak';
                $notifBody  = $decision === 'approved'
                    ? "Pengajuan cuti Anda dari {$leave->start_date->toDateString()} hingga {$leave->end_date->toDateString()} telah disetujui."
                    : "Pengajuan cuti Anda dari {$leave->start_date->toDateString()} hingga {$leave->end_date->toDateString()} ditolak." . ($note ? " Catatan: {$note}" : '');

                $this->notifications->notifyUser($leave->business_id, $employee->user_id, [
                    'type'  => $decision === 'approved' ? 'leave_approved' : 'leave_rejected',
                    'title' => $notifTitle,
                    'body'  => $notifBody,
                    'meta'  => ['leave_id' => $leave->id],
                ]);
            }

            return $leave->fresh(['employee:id,name,position', 'approver:id,name']);
        });
    }

    /**
     * Get or create a leave quota for an employee for a given year and type.
     * Default annual quota: 12 days.
     */
    public function getOrCreateQuota(Employee $employee, int $year, string $type = 'annual'): LeaveQuota
    {
        return LeaveQuota::firstOrCreate(
            [
                'business_id' => $employee->business_id,
                'employee_id' => $employee->id,
                'year'        => $year,
                'type'        => $type,
            ],
            [
                'quota_days'     => 12,
                'used_days'      => 0,
                'pending_days'   => 0,
                'carryover_days' => 0,
            ]
        );
    }

    /**
     * Get quota summary for an employee (current year by default).
     *
     * @return array{quota: LeaveQuota, approved_history: Collection}
     */
    public function quotaSummary(Employee $employee, int $year): array
    {
        $quota = $this->getOrCreateQuota($employee, $year, 'annual');

        $approvedHistory = LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->get();

        $pendingRequests = LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'annual')
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->orderByDesc('created_at')
            ->get();

        return [
            'quota'           => $quota,
            'approved_history' => $approvedHistory,
            'pending_requests' => $pendingRequests,
        ];
    }
}
