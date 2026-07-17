<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DailyStockDraftTest extends TestCase
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

    public function test_opening_a_future_date_is_stamped_as_draft(): void
    {
        $product = $this->createProduct('Nasi Kucing', 3000);
        $future = now()->addDays(3)->toDateString();

        $response = $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $future,
            'items'       => [['product_id' => $product, 'product_name' => 'Nasi Kucing', 'opening_qty' => 10]],
        ])->assertCreated();

        $this->assertSame('draft', $response->json('data.0.status'));

        $history = $this->withToken($this->token)
            ->getJson('/api/v1/inventory/daily-stock/history?business_id='.$this->businessId)
            ->assertOk();

        $this->assertSame('draft', $history->json('data.0.status'));
    }

    public function test_a_draft_day_can_be_deleted(): void
    {
        $product = $this->createProduct('Es Teh', 2000);
        $future = now()->addDays(2)->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $future,
            'items'       => [['product_id' => $product, 'product_name' => 'Es Teh', 'opening_qty' => 5]],
        ])->assertCreated();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/'.$future.'?business_id='.$this->businessId)
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/inventory/daily-stock/'.$future.'?business_id='.$this->businessId)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_non_draft_day_cannot_be_deleted(): void
    {
        $product = $this->createProduct('Kopi', 5000);
        $today = now()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Kopi', 'opening_qty' => 8]],
        ])->assertCreated();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/inventory/daily-stock/'.$today.'?business_id='.$this->businessId)
            ->assertStatus(422);
    }
}
