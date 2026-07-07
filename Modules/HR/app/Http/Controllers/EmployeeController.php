<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\AttendanceService;
use Modules\HR\Services\EmployeeService;

/**
 * @tags HR — Karyawan
 */
class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $service) {}

    private function businessId(): string
    {
        return (string) Auth::user()?->business_id;
    }

    private function findEmployee(string $id): Employee
    {
        return Employee::where('business_id', $this->businessId())->findOrFail($id);
    }

    /**
     * Daftar karyawan
     *
     * Filter opsional: `status` (active/inactive), `search` (nama).
     */
    public function index(Request $request): JsonResponse
    {
        $employees = $this->service->list($this->businessId(), [
            'status' => $request->input('status'),
            'search' => $request->input('search'),
        ]);

        return response()->json(['data' => $employees]);
    }

    /**
     * Tambah karyawan
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'user_id'  => 'nullable|uuid|exists:users,id',
            'position' => 'nullable|string|max:100',
            'phone'    => 'nullable|string|max:30',
            'email'    => 'nullable|email|max:255',
            'hired_at' => 'nullable|date',
            'note'     => 'nullable|string|max:255',
        ]);

        $employee = $this->service->create($this->businessId(), $data);

        return response()->json(['message' => 'Karyawan ditambahkan.', 'data' => $employee], 201);
    }

    /**
     * Profil karyawan
     */
    public function show(string $id): JsonResponse
    {
        $employee = $this->findEmployee($id)->load([
            'attendances' => fn ($q) => $q->orderByDesc('date')->limit(30),
            'leaveRequests' => fn ($q) => $q->orderByDesc('created_at')->limit(20),
        ]);

        return response()->json(['data' => $employee]);
    }

    /**
     * Ubah data karyawan
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'user_id'  => 'nullable|uuid|exists:users,id',
            'position' => 'nullable|string|max:100',
            'phone'    => 'nullable|string|max:30',
            'email'    => 'nullable|email|max:255',
            'hired_at' => 'nullable|date',
            'status'   => 'sometimes|in:active,inactive',
            'note'     => 'nullable|string|max:255',
        ]);

        $employee = $this->service->update($this->findEmployee($id), $data);

        return response()->json(['message' => 'Data karyawan diperbarui.', 'data' => $employee]);
    }

    /**
     * Ringkasan HR (dashboard & laporan)
     *
     * Jumlah karyawan aktif, kehadiran hari ini, dan cuti menunggu persetujuan.
     */
    public function summary(AttendanceService $attendance): JsonResponse
    {
        $businessId = $this->businessId();

        return response()->json([
            'data' => [
                'active_employees' => Employee::where('business_id', $businessId)->where('status', 'active')->count(),
                'present_today'    => \Modules\HR\Models\Attendance::where('business_id', $businessId)
                    ->whereDate('date', now()->toDateString())
                    ->whereNotNull('clock_in')
                    ->count(),
                'pending_leaves'   => LeaveRequest::where('business_id', $businessId)
                    ->where('status', 'pending')
                    ->count(),
            ],
        ]);
    }
}
