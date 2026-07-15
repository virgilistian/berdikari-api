<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\EmployeeService;
use Modules\HR\Services\LeaveService;

/**
 * @tags HR — Cuti & Izin
 */
class LeaveController extends Controller
{
    public function __construct(
        private LeaveService $service,
        private EmployeeService $employees,
    ) {}

    private function businessId(): string
    {
        return (string) Auth::user()?->business_id;
    }

    /**
     * Daftar pengajuan cuti
     *
     * Filter opsional: `status` (pending/approved/rejected), `employee_id`.
     */
    public function index(Request $request): JsonResponse
    {
        $leaves = $this->service->list($this->businessId(), [
            'status'      => $request->input('status'),
            'employee_id' => $request->input('employee_id'),
        ]);

        return response()->json(['data' => $leaves]);
    }

    /**
     * Pengajuan cuti saya
     */
    public function mine(): JsonResponse
    {
        $employee = $this->employees->findByUser($this->businessId(), (string) Auth::id());

        abort_if($employee === null, 422, 'Akun Anda belum terhubung dengan data karyawan. Hubungi pemilik usaha.');

        return response()->json([
            'data' => $this->service->list($this->businessId(), ['employee_id' => $employee->id]),
        ]);
    }

    /**
     * Ajukan cuti / izin
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'       => 'required|in:annual,sick,other',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:255',
        ]);

        $employee = $this->employees->findByUser($this->businessId(), (string) Auth::id());

        abort_if($employee === null, 422, 'Akun Anda belum terhubung dengan data karyawan. Hubungi pemilik usaha.');

        $leave = $this->service->submit($employee, $data);

        return response()->json(['message' => 'Pengajuan cuti terkirim.', 'data' => $leave], 201);
    }

    /**
     * Setujui pengajuan cuti
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->decide($request, $id, 'approved', 'Pengajuan cuti disetujui.');
    }

    /**
     * Tolak pengajuan cuti
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->decide($request, $id, 'rejected', 'Pengajuan cuti ditolak.');
    }

    /**
     * Kuota cuti saya
     *
     * Mengembalikan ringkasan kuota cuti tahunan karyawan untuk tahun tertentu.
     *
     * @queryParam year integer Tahun (default: tahun ini). Example: 2026
     */
    public function quota(Request $request): JsonResponse
    {
        $employee = $this->employees->findByUser($this->businessId(), (string) Auth::id());

        abort_if($employee === null, 422, 'Akun Anda belum terhubung dengan data karyawan. Hubungi pemilik usaha.');

        $year = (int) $request->input('year', now()->year);

        $summary = $this->service->quotaSummary($employee, $year);

        return response()->json(['data' => $summary]);
    }

    /**
     * Kuota cuti karyawan (untuk manajer)
     *
     * @queryParam year integer Tahun. Example: 2026
     */
    public function employeeQuota(Request $request, string $employeeId): JsonResponse
    {
        $this->authorize('employee.view');

        $employee = \Modules\HR\Models\Employee::where('business_id', $this->businessId())
            ->findOrFail($employeeId);

        $year = (int) $request->input('year', now()->year);

        $summary = $this->service->quotaSummary($employee, $year);

        return response()->json(['data' => $summary]);
    }

    private function decide(Request $request, string $id, string $decision, string $message): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);

        $leave = LeaveRequest::with('employee')
            ->where('business_id', $this->businessId())
            ->findOrFail($id);

        $leave = $this->service->decide($leave, (string) Auth::id(), $decision, $data['note'] ?? null);

        return response()->json(['message' => $message, 'data' => $leave]);
    }
}
