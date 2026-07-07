<?php

namespace Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\HR\Models\Employee;
use Modules\HR\Services\AttendanceService;
use Modules\HR\Services\EmployeeService;

/**
 * @tags HR — Absensi
 */
class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $service,
        private EmployeeService $employees,
    ) {}

    private function businessId(): string
    {
        return (string) Auth::user()?->business_id;
    }

    /**
     * The employee profile linked to the authenticated user — required for
     * all self-service attendance actions.
     */
    private function selfEmployee(): Employee
    {
        $employee = $this->employees->findByUser($this->businessId(), (string) Auth::id());

        abort_if($employee === null, 422, 'Akun Anda belum terhubung dengan data karyawan. Hubungi pemilik usaha.');

        return $employee;
    }

    /**
     * Riwayat absensi
     *
     * Filter opsional: `employee_id`, `from`, `to` (Y-m-d).
     */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->service->list($this->businessId(), [
            'employee_id' => $request->input('employee_id'),
            'from'        => $request->input('from'),
            'to'          => $request->input('to'),
        ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Status absensi saya hari ini
     */
    public function me(): JsonResponse
    {
        $employee = $this->selfEmployee();

        return response()->json([
            'data' => [
                'employee' => $employee->only(['id', 'name', 'position']),
                'today'    => $this->service->today($employee),
                'history'  => $this->service->list($this->businessId(), ['employee_id' => $employee->id]),
            ],
        ]);
    }

    /**
     * Absen masuk
     */
    public function clockIn(Request $request): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);

        $attendance = $this->service->clockIn($this->selfEmployee(), $data['note'] ?? null);

        return response()->json(['message' => 'Absen masuk tercatat.', 'data' => $attendance], 201);
    }

    /**
     * Absen pulang
     */
    public function clockOut(Request $request): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|max:255']);

        $attendance = $this->service->clockOut($this->selfEmployee(), $data['note'] ?? null);

        return response()->json(['message' => 'Absen pulang tercatat.', 'data' => $attendance]);
    }
}
