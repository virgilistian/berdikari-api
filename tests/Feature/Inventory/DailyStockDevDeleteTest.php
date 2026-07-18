<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DailyStockDevDeleteTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(['inventory.view', 'inventory.create'], 'owner'));
    }

    private function createProduct(string $name, float $price): string
    {
        return $this->withToken($this->token)->postJson('/api/v1/catalog/products', [
            'name' => $name, 'price' => $price, 'purchase_price' => $price / 2,
        ])->json('data.id');
    }

    public function test_an_open_day_can_be_deleted_without_force(): void
    {
        $product = $this->createProduct('Nasi Kucing', 3000);
        $today = now()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Nasi Kucing', 'opening_qty' => 10]],
        ])->assertCreated();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/dev/'.$today, ['business_id' => $this->businessId])
            ->assertOk()
            ->assertJsonPath('data.deleted_count', 1)
            ->assertJsonPath('data.was_closed', false);

        $this->withToken($this->token)
            ->getJson('/api/v1/inventory/daily-stock/'.$today.'?business_id='.$this->businessId)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_closed_day_is_refused_without_force(): void
    {
        $product = $this->createProduct('Es Teh', 2000);
        $today = now()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Es Teh', 'opening_qty' => 5]],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/close', [
            'business_id' => $this->businessId,
            'date'        => $today,
        ])->assertOk();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/dev/'.$today, ['business_id' => $this->businessId])
            ->assertStatus(409);

        $this->withToken($this->token)
            ->getJson('/api/v1/inventory/daily-stock/'.$today.'?business_id='.$this->businessId)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_closed_day_can_be_force_deleted(): void
    {
        $product = $this->createProduct('Kopi', 5000);
        $today = now()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Kopi', 'opening_qty' => 8]],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/close', [
            'business_id' => $this->businessId,
            'date'        => $today,
        ])->assertOk();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/dev/'.$today, [
                'business_id' => $this->businessId,
                'force'       => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.was_closed', true);

        $this->withToken($this->token)
            ->getJson('/api/v1/inventory/daily-stock/'.$today.'?business_id='.$this->businessId)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Realtime inventory valuation synced at close-time is untouched — deleting
        // the historical daily-stock row does not roll back live stock quantity.
        $this->withToken($this->token)
            ->getJson('/api/v1/inventory?business_id='.$this->businessId)
            ->assertOk()
            ->assertJsonPath('data.0.quantity', 8);
    }

    public function test_returns_404_when_nothing_to_delete(): void
    {
        $missingDate = now()->subDays(30)->toDateString();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/dev/'.$missingDate, ['business_id' => $this->businessId])
            ->assertStatus(404);
    }

    public function test_requires_inventory_create_permission(): void
    {
        $viewerToken = $this->tokenFor($this->makeUser(['inventory.view'], 'viewer'));
        $today = now()->toDateString();

        $this->withToken($viewerToken)
            ->deleteJson('/api/v1/inventory/daily-stock/dev/'.$today, ['business_id' => $this->businessId])
            ->assertStatus(403);
    }
}
