<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    // ── List roles — require role.assign or user.manage ──────────────────────

    public function test_owner_can_list_roles(): void
    {
        $owner = $this->makeUser(['role.assign'], 'owner');
        $this->makeRole('cashier', ['pos.view', 'pos.open']);

        $this->withToken($this->tokenFor($owner))->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_viewer_cannot_list_roles(): void
    {
        $viewer = $this->makeUser(['finance.view'], 'viewer');

        $this->withToken($this->tokenFor($viewer))->getJson('/api/v1/roles')
            ->assertForbidden();
    }

    // ── Sync role permissions ─────────────────────────────────────────────────

    public function test_owner_can_sync_role_permissions(): void
    {
        $owner = $this->makeUser(['role.assign'], 'owner');
        $role = $this->makeRole('cashier', ['pos.view']);

        $this->withToken($this->tokenFor($owner))->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['pos.view', 'pos.open', 'pos.close'],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.permissions');
    }

    public function test_sync_rejects_unknown_permission(): void
    {
        $owner = $this->makeUser(['role.assign'], 'owner');
        $role = $this->makeRole('cashier', ['pos.view']);

        $this->withToken($this->tokenFor($owner))->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['not.a.real.permission'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_cashier_cannot_sync_role_permissions(): void
    {
        $cashier = $this->makeUser(['pos.view'], 'cashier');
        $role = $this->makeRole('cashier', ['pos.view']);

        $this->withToken($this->tokenFor($cashier))->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['pos.view', 'pos.open'],
        ])->assertForbidden();
    }

    public function test_super_admin_role_cannot_be_modified(): void
    {
        $owner = $this->makeUser(['role.assign'], 'owner');
        $role = $this->makeRole('super-admin', []);

        $this->withToken($this->tokenFor($owner))->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['pos.view'],
        ])->assertForbidden();
    }

    // ── Assign / remove roles on users ────────────────────────────────────────

    public function test_owner_can_assign_and_remove_a_role_from_a_user(): void
    {
        $owner = $this->makeUser(['role.assign'], 'owner');
        $target = $this->makeUser([], 'cashier');
        $this->makeRole('cashier', ['pos.view']);
        $token = $this->tokenFor($owner);

        // Assign
        $this->withToken($token)->postJson("/api/v1/users/{$target->id}/roles", [
            'role' => 'cashier',
        ])->assertOk()->assertJsonPath('success', true);

        setPermissionsTeamId($this->businessId);
        $this->assertTrue($target->fresh()->hasRole('cashier'));

        // Remove
        $this->withToken($token)->deleteJson("/api/v1/users/{$target->id}/roles/cashier")
            ->assertOk()->assertJsonPath('success', true);

        setPermissionsTeamId($this->businessId);
        $this->assertFalse($target->fresh()->hasRole('cashier'));
    }

    public function test_cashier_cannot_assign_roles(): void
    {
        $cashier = $this->makeUser(['pos.view'], 'cashier');
        $target = $this->makeUser([], 'cashier');
        $this->makeRole('cashier', ['pos.view']);

        $this->withToken($this->tokenFor($cashier))->postJson("/api/v1/users/{$target->id}/roles", [
            'role' => 'cashier',
        ])->assertForbidden();
    }
}
