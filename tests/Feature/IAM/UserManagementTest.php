<?php

namespace Tests\Feature\IAM;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): array
    {
        $user = User::create([
            'business_id' => null,
            'name' => 'Pemilik',
            'email' => 'owner@test.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    private function createCashier(string $businessId): User
    {
        return User::create([
            'business_id' => $businessId,
            'name' => 'Kasir',
            'email' => 'kasir@test.com',
            'password' => bcrypt('password'),
            'role' => 'cashier',
        ]);
    }

    public function test_owner_can_list_users_in_their_business(): void
    {
        [$owner, $token] = $this->createOwner();
        $this->createCashier($owner->business_id);

        $response = $this->withToken($token)->getJson('/api/v1/users');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_owner_can_create_a_new_user(): void
    {
        [$owner, $token] = $this->createOwner();

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Karyawan Baru',
            'email' => 'baru@test.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'baru@test.com');
    }

    public function test_cashier_cannot_create_users(): void
    {
        [$owner, $ownerToken] = $this->createOwner();
        $cashier = $this->createCashier($owner->business_id);
        $cashierToken = $cashier->createToken('test')->plainTextToken;

        $response = $this->withToken($cashierToken)->postJson('/api/v1/users', [
            'name' => 'Baru',
            'email' => 'baru2@test.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_unauthenticated_request_to_users_returns_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}


    public function test_owner_can_list_users_in_their_business(): void
    {
        [$owner, $token] = $this->createOwner();
        $this->createCashier($owner->business_id);

        $response = $this->withToken($token)->getJson('/api/v1/users');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_owner_can_create_a_new_user(): void
    {
        [$owner, $token] = $this->createOwner();

        $response = $this->withToken($token)->postJson('/api/v1/users', [
            'name' => 'Karyawan Baru',
            'email' => 'baru@test.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'baru@test.com');
    }

    public function test_cashier_cannot_create_users(): void
    {
        [$owner, $ownerToken] = $this->createOwner();
        $cashier = $this->createCashier($owner->business_id);
        $cashierToken = $cashier->createToken('test')->plainTextToken;

        $response = $this->withToken($cashierToken)->postJson('/api/v1/users', [
            'name' => 'Baru',
            'email' => 'baru2@test.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_unauthenticated_request_to_users_returns_401(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
