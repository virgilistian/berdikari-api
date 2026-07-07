<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser([], 'owner'));
    }

    private function createProduct(string $name, float $price, float $purchase): string
    {
        return $this->withToken($this->token)->postJson('/api/v1/catalog/products', [
            'name' => $name, 'price' => $price, 'purchase_price' => $purchase,
        ])->json('data.id');
    }

    public function test_receive_stock_increases_quantity_and_records_movement(): void
    {
        $product = $this->createProduct('Gula', 10000, 8000);

        $this->withToken($this->token)->postJson('/api/v1/inventory/receive', [
            'product_id' => $product, 'quantity' => 20, 'unit_cost' => 8000,
        ])->assertCreated()->assertJsonPath('data.quantity', 20);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product, 'type' => 'in', 'quantity' => 20,
        ]);

        $this->withToken($this->token)->getJson("/api/v1/inventory/{$product}/movements")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_stock_valuation_summary(): void
    {
        $product = $this->createProduct('Beras', 15000, 12000);

        $this->withToken($this->token)->postJson('/api/v1/inventory/receive', [
            'product_id' => $product, 'quantity' => 10,
        ])->assertCreated();

        $this->withToken($this->token)->getJson('/api/v1/inventory/summary')
            ->assertOk()
            ->assertJsonPath('data.total_products', 1)
            ->assertJsonPath('data.total_quantity', 10)
            ->assertJsonPath('data.stock_value', 120000)   // 10 * 12000 purchase
            ->assertJsonPath('data.retail_value', 150000); // 10 * 15000 selling
    }

    public function test_low_stock_alert_flags_products_at_or_below_threshold(): void
    {
        $product = $this->createProduct('Teh', 2000, 1000);

        $this->withToken($this->token)->postJson('/api/v1/inventory/receive', [
            'product_id' => $product, 'quantity' => 3,
        ])->assertCreated();

        $this->withToken($this->token)->putJson("/api/v1/inventory/{$product}/min-stock", [
            'min_stock' => 5,
        ])->assertOk();

        $this->withToken($this->token)->getJson('/api/v1/inventory/low-stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $product)
            ->assertJsonPath('data.0.is_low', true);
    }

    public function test_stock_adjustment_sets_absolute_quantity_and_logs_delta(): void
    {
        $product = $this->createProduct('Kopi', 5000, 3000);

        $this->withToken($this->token)->postJson('/api/v1/inventory/receive', [
            'product_id' => $product, 'quantity' => 10,
        ])->assertCreated();

        // Correct down to 7 (delta -3)
        $this->withToken($this->token)->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product, 'quantity' => 7, 'reason' => 'Rusak',
        ])->assertOk()->assertJsonPath('data.quantity', 7);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product, 'type' => 'adjustment', 'quantity' => -3,
        ]);
    }
}
