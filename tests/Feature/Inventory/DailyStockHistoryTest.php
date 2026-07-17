<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class DailyStockHistoryTest extends TestCase
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

    public function test_history_aggregates_daily_stock_per_date_newest_first(): void
    {
        $gula = $this->createProduct('Gula', 10000);
        $kopi = $this->createProduct('Kopi', 5000);

        $older = '2026-07-10';
        $newer = '2026-07-12';

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $older,
            'items'       => [
                ['product_id' => $gula, 'product_name' => 'Gula', 'opening_qty' => 10],
            ],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $newer,
            'items'       => [
                ['product_id' => $gula, 'product_name' => 'Gula', 'opening_qty' => 20],
                ['product_id' => $kopi, 'product_name' => 'Kopi', 'opening_qty' => 15],
            ],
        ])->assertCreated();

        // Close the older day so it carries a closing total; the newer day stays open.
        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/close', [
            'business_id' => $this->businessId,
            'date'        => $older,
        ])->assertOk();

        $response = $this->withToken($this->token)->getJson(
            '/api/v1/inventory/daily-stock/history?business_id='.$this->businessId
        )->assertOk()->assertJsonCount(2, 'data');

        $data = $response->json('data');

        // Newest first.
        $this->assertSame($newer, $data[0]['date']);
        $this->assertSame(2, $data[0]['total_menu_items']);
        $this->assertSame(35, $data[0]['total_opening_qty']);
        $this->assertSame(0, $data[0]['total_closing_qty']);
        $this->assertSame('open', $data[0]['status']);

        $this->assertSame($older, $data[1]['date']);
        $this->assertSame(1, $data[1]['total_menu_items']);
        $this->assertSame(10, $data[1]['total_opening_qty']);
        $this->assertSame(10, $data[1]['total_closing_qty']);
        $this->assertSame('closed', $data[1]['status']);
    }

    public function test_history_is_scoped_to_the_requesting_business(): void
    {
        $product = $this->createProduct('Teh', 2000);

        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => '2026-07-15',
            'items'       => [['product_id' => $product, 'product_name' => 'Teh', 'opening_qty' => 4]],
        ])->assertCreated();

        $otherBusinessId = '019f2e4c-0000-7000-8000-000000000098';
        DB::table('businesses')->insertOrIgnore([
            'id' => $otherBusinessId, 'name' => 'Other Business', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherUser = User::create([
            'business_id' => $otherBusinessId,
            'name'        => 'Other Owner',
            'email'       => 'other'.uniqid().'@test.com',
            'password'    => bcrypt('password'),
            'role'        => 'owner',
        ]);

        $this->actingWithToken($this->tokenFor($otherUser))
            ->getJson('/api/v1/inventory/daily-stock/history?business_id='.$otherBusinessId)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
