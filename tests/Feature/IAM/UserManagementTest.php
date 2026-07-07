<?php

namespace Tests\Feature\IAM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    // ── Read (index / show) — require user.manage ────────────────────────────

    public function test_owner_can_list_users_in_their_business(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');
        $this->makeUser([], 'cashier');

        $response = $this->withToken($this->tokenFor($owner))->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_viewer_without_user_manage_cannot_list_users(): void
    {
        $viewer = $this->makeUser(['finance.view'], 'viewer');

        $this->withToken($this->tokenFor($viewer))->getJson('/api/v1/users')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_owner_can_show_a_user(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');
        $target = $this->makeUser([], 'cashier');

        $this->withToken($this->tokenFor($owner))->getJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);
    }

    public function test_viewer_cannot_show_a_user(): void
    {
        $viewer = $this->makeUser(['finance.view'], 'viewer');
        $target = $this->makeUser([], 'cashier');

        $this->withToken($this->tokenFor($viewer))->getJson("/api/v1/users/{$target->id}")
            ->assertForbidden();
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_owner_can_create_a_new_user(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');
        $this->makeRole('cashier', ['pos.view']);

        $response = $this->withToken($this->tokenFor($owner))->postJson('/api/v1/users', [
            'name'     => 'Karyawan Baru',
            'email'    => 'baru@test.com',
            'password' => 'password123',
            'role'     => 'cashier',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'baru@test.com')
            ->assertJsonPath('data.roles', ['cashier']);

        $this->assertDatabaseHas('users', ['email' => 'baru@test.com']);
    }

    public function test_cashier_cannot_create_users(): void
    {
        $cashier = $this->makeUser(['pos.view'], 'cashier');

        $this->withToken($this->tokenFor($cashier))->postJson('/api/v1/users', [
            'name'     => 'Baru',
            'email'    => 'baru2@test.com',
            'password' => 'password123',
            'role'     => 'cashier',
        ])->assertForbidden()->assertJsonPath('success', false);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_owner_can_update_a_user(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');
        $target = $this->makeUser([], 'cashier');

        $this->withToken($this->tokenFor($owner))->putJson("/api/v1/users/{$target->id}", [
            'name' => 'Nama Diperbarui',
        ])->assertOk()->assertJsonPath('data.name', 'Nama Diperbarui');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nama Diperbarui']);
    }

    public function test_cashier_cannot_update_users(): void
    {
        $cashier = $this->makeUser(['pos.view'], 'cashier');
        $target = $this->makeUser([], 'cashier');

        $this->withToken($this->tokenFor($cashier))->putJson("/api/v1/users/{$target->id}", [
            'name' => 'Nakal',
        ])->assertForbidden();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_owner_can_delete_a_user(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');
        $target = $this->makeUser([], 'cashier');

        $this->withToken($this->tokenFor($owner))->deleteJson("/api/v1/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_owner_cannot_delete_their_own_account(): void
    {
        $owner = $this->makeUser(['user.manage'], 'owner');

        $this->withToken($this->tokenFor($owner))->deleteJson("/api/v1/users/{$owner->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    // ── Auth boundary ─────────────────────────────────────────────────────────

    public function test_unauthenticated_request_to_users_returns_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
