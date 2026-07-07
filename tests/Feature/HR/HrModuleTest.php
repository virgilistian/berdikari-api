<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class HrModuleTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function managerToken(): string
    {
        return $this->tokenFor($this->makeUser([
            'employee.view', 'employee.create', 'employee.update',
            'attendance.view', 'leave.view', 'leave.approve',
        ], 'manager'));
    }

    /** An employee profile linked to a fresh user; returns [token, employee]. */
    private function staffWithProfile(): array
    {
        $user = $this->makeUser(['attendance.create', 'leave.create'], 'staff');

        $employee = Employee::create([
            'business_id' => $this->businessId,
            'user_id'     => $user->id,
            'name'        => $user->name,
            'position'    => 'Kasir',
            'status'      => 'active',
        ]);

        return [$this->tokenFor($user), $employee];
    }

    // ── Employees ────────────────────────────────────────────────────────────

    public function test_manager_can_create_and_list_employees(): void
    {
        $token = $this->managerToken();

        $this->actingWithToken($token)->postJson('/api/v1/hr/employees', [
            'name'     => 'Budi Santoso',
            'position' => 'Staf Dapur',
            'phone'    => '0812345678',
        ])->assertCreated()->assertJsonPath('data.name', 'Budi Santoso');

        $this->actingWithToken($token)->getJson('/api/v1/hr/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_employee_creation_requires_permission(): void
    {
        $token = $this->tokenFor($this->makeUser(['employee.view'], 'viewer'));

        $this->actingWithToken($token)->postJson('/api/v1/hr/employees', [
            'name' => 'Tanpa Izin',
        ])->assertForbidden();
    }

    public function test_employee_list_requires_permission(): void
    {
        $token = $this->tokenFor($this->makeUser([], 'staff'));

        $this->actingWithToken($token)->getJson('/api/v1/hr/employees')->assertForbidden();
    }

    public function test_manager_can_update_employee_and_deactivate(): void
    {
        $token = $this->managerToken();

        $id = $this->actingWithToken($token)->postJson('/api/v1/hr/employees', [
            'name' => 'Siti Aminah',
        ])->json('data.id');

        $this->actingWithToken($token)->putJson("/api/v1/hr/employees/{$id}", [
            'position' => 'Supervisor',
            'status'   => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.position', 'Supervisor')
            ->assertJsonPath('data.status', 'inactive');
    }

    // ── Attendance ───────────────────────────────────────────────────────────

    public function test_linked_employee_can_clock_in_and_out(): void
    {
        [$token] = $this->staffWithProfile();

        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('data.status', 'present');

        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-out')
            ->assertOk();

        $me = $this->actingWithToken($token)->getJson('/api/v1/hr/attendance/me');
        $me->assertOk();
        $this->assertNotNull($me->json('data.today.clock_in'));
        $this->assertNotNull($me->json('data.today.clock_out'));
    }

    public function test_double_clock_in_is_rejected(): void
    {
        [$token] = $this->staffWithProfile();

        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-in')->assertCreated();
        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_clock_out_without_clock_in_is_rejected(): void
    {
        [$token] = $this->staffWithProfile();

        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-out')
            ->assertStatus(422);
    }

    public function test_user_without_employee_profile_cannot_clock_in(): void
    {
        $token = $this->tokenFor($this->makeUser(['attendance.create'], 'staff'));

        $this->actingWithToken($token)->postJson('/api/v1/hr/attendance/clock-in')
            ->assertStatus(422);
    }

    public function test_attendance_history_requires_view_permission(): void
    {
        [$token] = $this->staffWithProfile();

        // attendance.create alone does not grant the full history list
        $this->actingWithToken($token)->getJson('/api/v1/hr/attendance')->assertForbidden();

        $this->actingWithToken($this->managerToken())->getJson('/api/v1/hr/attendance')->assertOk();
    }

    // ── Leave workflow ───────────────────────────────────────────────────────

    public function test_leave_request_and_approval_workflow(): void
    {
        [$staffToken, $employee] = $this->staffWithProfile();

        $leaveId = $this->actingWithToken($staffToken)->postJson('/api/v1/hr/leaves', [
            'type'       => 'annual',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(4)->toDateString(),
            'reason'     => 'Acara keluarga',
        ])->assertCreated()->json('data.id');

        // Staff cannot approve (no leave.approve)
        $this->actingWithToken($staffToken)
            ->postJson("/api/v1/hr/leaves/{$leaveId}/approve")
            ->assertForbidden();

        // Manager approves
        $this->actingWithToken($this->managerToken())
            ->postJson("/api/v1/hr/leaves/{$leaveId}/approve", ['note' => 'Selamat berlibur'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // Already decided — second decision rejected
        $this->actingWithToken($this->managerToken())
            ->postJson("/api/v1/hr/leaves/{$leaveId}/reject")
            ->assertStatus(422);
    }

    public function test_overlapping_leave_request_is_rejected(): void
    {
        [$staffToken] = $this->staffWithProfile();

        $payload = [
            'type'       => 'annual',
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date'   => now()->addDays(2)->toDateString(),
        ];

        $this->actingWithToken($staffToken)->postJson('/api/v1/hr/leaves', $payload)->assertCreated();
        $this->actingWithToken($staffToken)->postJson('/api/v1/hr/leaves', $payload)->assertStatus(422);
    }

    public function test_approver_cannot_approve_own_leave_request(): void
    {
        // A manager who is also a linked employee
        $manager = $this->makeUser([
            'employee.view', 'leave.view', 'leave.approve', 'leave.create',
        ], 'manager');
        Employee::create([
            'business_id' => $this->businessId,
            'user_id'     => $manager->id,
            'name'        => $manager->name,
            'status'      => 'active',
        ]);
        $token = $this->tokenFor($manager);

        $leaveId = $this->actingWithToken($token)->postJson('/api/v1/hr/leaves', [
            'type'       => 'sick',
            'start_date' => now()->toDateString(),
            'end_date'   => now()->toDateString(),
        ])->assertCreated()->json('data.id');

        $this->actingWithToken($token)
            ->postJson("/api/v1/hr/leaves/{$leaveId}/approve")
            ->assertStatus(422);
    }

    // ── Summary ──────────────────────────────────────────────────────────────

    public function test_hr_summary_returns_counts(): void
    {
        [$staffToken] = $this->staffWithProfile();
        $this->actingWithToken($staffToken)->postJson('/api/v1/hr/attendance/clock-in')->assertCreated();

        $response = $this->actingWithToken($this->managerToken())->getJson('/api/v1/hr/summary');

        $response->assertOk()
            ->assertJsonPath('data.active_employees', 1)
            ->assertJsonPath('data.present_today', 1)
            ->assertJsonPath('data.pending_leaves', 0);
    }
}
