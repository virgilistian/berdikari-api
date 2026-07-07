<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    private function authed(): string
    {
        return $this->tokenFor($this->makeUser([], 'owner'));
    }

    public function test_can_create_product_with_selling_purchase_and_cost_price(): void
    {
        $token = $this->authed();

        $response = $this->withToken($token)->postJson('/api/v1/catalog/products', [
            'name'           => 'Nasi Kucing Teri',
            'price'          => 3000,
            'purchase_price' => 1800,
            'cost_price'     => 2000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nasi Kucing Teri')
            ->assertJsonPath('data.price', '3000.00')
            ->assertJsonPath('data.purchase_price', '1800.00')
            ->assertJsonPath('data.cost_price', '2000.00')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', [
            'name'           => 'Nasi Kucing Teri',
            'purchase_price' => 1800,
            'cost_price'     => 2000,
        ]);
    }

    public function test_product_list_is_scoped_to_business(): void
    {
        $token = $this->authed();

        $this->withToken($token)->postJson('/api/v1/catalog/products', [
            'name' => 'Es Teh', 'price' => 3000,
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/catalog/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Es Teh');
    }

    public function test_can_update_product_pricing(): void
    {
        $token = $this->authed();

        $id = $this->withToken($token)->postJson('/api/v1/catalog/products', [
            'name' => 'Kopi', 'price' => 4000,
        ])->json('data.id');

        $this->withToken($token)->putJson("/api/v1/catalog/products/{$id}", [
            'price' => 5000, 'purchase_price' => 2500,
        ])->assertOk()
            ->assertJsonPath('data.price', '5000.00')
            ->assertJsonPath('data.purchase_price', '2500.00');
    }

    public function test_product_requires_name_and_price(): void
    {
        $token = $this->authed();

        $this->withToken($token)->postJson('/api/v1/catalog/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price']);
    }
}
