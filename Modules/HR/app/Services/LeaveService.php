<?php

namespace Modules\HR\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;

class LeaveService
{
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

        return LeaveRequest::create([
            'business_id' => $employee->business_id,
            'employee_id' => $employee->id,
            'type'        => $data['type'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'reason'      => $data['reason'] ?? null,
            'status'      => 'pending',
        ]);
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

        $leave->update([
            'status'        => $decision,
            'approved_by'   => $deciderUserId,
            'decided_at'    => now(),
            'decision_note' => $note,
        ]);

        return $leave->fresh(['employee:id,name,position', 'approver:id,name']);
    }
}
