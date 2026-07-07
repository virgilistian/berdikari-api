<?php

namespace Tests\Feature\IAM;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $this->makeUser(['user.manage'], 'owner', 'test@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'roles', 'permissions'],
                ],
                'message',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.permissions', ['user.manage']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $this->makeUser([], 'owner', 'test@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = $this->makeUser(['finance.view'], 'cashier');
        $token = $this->tokenFor($user);

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'cashier')
            ->assertJsonPath('data.permissions', ['finance.view']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = $this->makeUser();
        $token = $this->tokenFor($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertOk();

        // The access token record must be revoked (deleted).
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_unauthenticated_request_to_logout_returns_401(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
