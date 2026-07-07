<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    // ── Profile update ─────────────────────────────────────────────────────────

    public function test_user_can_update_their_profile(): void
    {
        $user = $this->makeUser(['finance.view'], 'cashier', 'me@test.com');

        $this->withToken($this->tokenFor($user))->putJson('/api/v1/auth/profile', [
            'name'  => 'Nama Baru',
            'email' => 'new@test.com',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.email', 'new@test.com');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@test.com']);
    }

    public function test_profile_update_rejects_email_used_by_another_user(): void
    {
        $this->makeUser([], 'cashier', 'taken@test.com');
        $user = $this->makeUser(['finance.view'], 'cashier', 'me@test.com');

        $this->withToken($this->tokenFor($user))->putJson('/api/v1/auth/profile', [
            'name'  => 'X',
            'email' => 'taken@test.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/profile', ['name' => 'X', 'email' => 'x@test.com'])
            ->assertUnauthorized();
    }

    // ── Password change ─────────────────────────────────────────────────────────

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $user = $this->makeUser([], 'cashier', 'pw@test.com');

        $this->withToken($this->tokenFor($user))->putJson('/api/v1/auth/password', [
            'current_password'      => 'password',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));

        // Can log in with the new password
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'pw@test.com',
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->makeUser([], 'cashier', 'pw2@test.com');

        $this->withToken($this->tokenFor($user))->putJson('/api/v1/auth/password', [
            'current_password'      => 'wrong-one',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422)->assertJsonPath('success', false);

        // Password unchanged
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_change_requires_matching_confirmation(): void
    {
        $user = $this->makeUser([], 'cashier', 'pw3@test.com');

        $this->withToken($this->tokenFor($user))->putJson('/api/v1/auth/password', [
            'current_password'      => 'password',
            'password'              => 'newpassword123',
            'password_confirmation' => 'different456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }
}
