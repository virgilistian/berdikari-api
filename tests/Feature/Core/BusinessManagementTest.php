<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class BusinessManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_owner_can_create_a_second_business_and_it_becomes_active(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $token = $this->tokenFor($owner);

        $response = $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Kolam Renang Berdikari',
            'type' => 'swimming_pool',
            'code' => 'kolam-1',
            'address' => 'Jl. Renang No. 1',
        ])->assertCreated();

        $secondBusinessId = $response->json('data.id');

        $this->assertDatabaseHas('businesses', ['id' => $secondBusinessId, 'code' => 'kolam-1']);
        $this->assertDatabaseHas('business_user', ['business_id' => $secondBusinessId, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'business_id' => $secondBusinessId]);
    }

    public function test_index_lists_only_active_businesses_the_user_belongs_to(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $token = $this->tokenFor($owner);

        $second = $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Toko Kedua', 'type' => 'retail', 'code' => 'toko-2',
        ])->assertCreated()->json('data.id');

        // Owner switched into the new business by store(); switch back to the demo one.
        $this->withToken($token)->postJson("/api/v1/businesses/{$this->businessId}/switch")->assertOk();

        $this->withToken($token)->deleteJson("/api/v1/businesses/{$second}")->assertOk();

        $index = $this->withToken($token)->getJson('/api/v1/businesses')->assertOk();
        $ids = collect($index->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->businessId));
        $this->assertFalse($ids->contains($second), 'deactivated business must not appear in the default list');

        $withInactive = $this->withToken($token)->getJson('/api/v1/businesses?include_inactive=1')->assertOk();
        $this->assertTrue(collect($withInactive->json('data'))->pluck('id')->contains($second));
    }

    public function test_member_can_switch_active_business_and_it_scopes_finance_data(): void
    {
        $owner = $this->makeUser(['business.manage', 'finance.view', 'finance.create'], 'owner');
        $token = $this->tokenFor($owner);

        // Record a finance entry in the original (demo) business.
        $this->withToken($token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 50000, 'category' => 'Penjualan',
        ])->assertCreated();

        $second = $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Bisnis Kedua', 'type' => 'restaurant', 'code' => 'bisnis-2',
        ])->assertCreated()->json('data.id'); // store() auto-switches into this one

        // Fresh business has no finance entries of its own.
        $this->withToken($token)->getJson('/api/v1/finance')->assertOk()->assertJsonCount(0, 'data');

        // Switching back restores visibility into the first business's data.
        $this->withToken($token)->postJson("/api/v1/businesses/{$this->businessId}/switch")
            ->assertOk()
            ->assertJsonPath('data.business_id', $this->businessId);

        $this->withToken($token)->getJson('/api/v1/finance')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_switch_to_a_business_not_a_member_of(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $stranger = '019f2e4c-0000-7000-8000-000000000099';

        DB::table('businesses')->insert([
            'id' => $stranger,
            'name' => 'Bukan Milik Saya',
            'code' => 'bukan-punya',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->tokenFor($owner))
            ->postJson("/api/v1/businesses/{$stranger}/switch")
            ->assertNotFound();
    }

    public function test_deactivating_the_active_business_switches_to_another_owned_business(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $token = $this->tokenFor($owner);

        $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Bisnis Cadangan', 'type' => 'retail', 'code' => 'cadangan',
        ])->assertCreated();

        // Owner is now active in the demo business again? No — store() switches
        // into the new one. Deactivate the (now active) new business.
        $me = $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.business_id');

        $this->withToken($token)->deleteJson("/api/v1/businesses/{$me}")->assertOk();

        $after = $this->withToken($token)->getJson('/api/v1/auth/me')->json('data.business_id');
        $this->assertSame($this->businessId, $after, 'owner should fall back to the remaining owned business');
    }

    public function test_hard_delete_is_blocked_when_business_has_related_data(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $this->makeUser([], 'cashier'); // a second member of the demo business

        $this->withToken($this->tokenFor($owner))
            ->deleteJson("/api/v1/businesses/{$this->businessId}?permanent=1")
            ->assertStatus(422);

        $this->assertDatabaseHas('businesses', ['id' => $this->businessId]);
    }

    public function test_hard_delete_succeeds_for_an_empty_business(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $token = $this->tokenFor($owner);

        $empty = $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Bisnis Kosong', 'type' => 'retail', 'code' => 'kosong',
        ])->assertCreated()->json('data.id');

        // Switch away first so the FK on users.business_id doesn't block/cascade unexpectedly.
        $this->withToken($token)->postJson("/api/v1/businesses/{$this->businessId}/switch")->assertOk();

        $this->withToken($token)
            ->deleteJson("/api/v1/businesses/{$empty}?permanent=1")
            ->assertOk();

        $this->assertDatabaseMissing('businesses', ['id' => $empty]);
    }

    public function test_business_without_manage_permission_cannot_create_or_delete(): void
    {
        $cashier = $this->makeUser(['pos.view'], 'cashier');
        $token = $this->tokenFor($cashier);

        $this->withToken($token)->postJson('/api/v1/businesses', [
            'name' => 'Tidak Boleh', 'type' => 'retail', 'code' => 'tidak-boleh',
        ])->assertForbidden();

        $this->withToken($token)->deleteJson("/api/v1/businesses/{$this->businessId}")->assertForbidden();
    }

    public function test_switching_does_not_require_business_manage_permission(): void
    {
        $owner = $this->makeUser(['business.manage'], 'owner');
        $ownerToken = $this->tokenFor($owner);
        $second = $this->withToken($ownerToken)->postJson('/api/v1/businesses', [
            'name' => 'Bisnis Bersama', 'type' => 'retail', 'code' => 'bersama',
        ])->assertCreated()->json('data.id');

        // Attach a plain cashier (no business.manage) to the second business directly.
        $cashier = $this->makeUser(['pos.view'], 'cashier');
        DB::table('business_user')->insert([
            'business_id' => $second, 'user_id' => $cashier->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withToken($this->tokenFor($cashier))
            ->postJson("/api/v1/businesses/{$second}/switch")
            ->assertOk();
    }
}
