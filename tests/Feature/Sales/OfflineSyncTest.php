<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Sales\Models\SaleOrder;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Offline-first POS: orders carry a client-generated UUID so the offline
 * queue can be re-submitted safely after reconnect without duplicates.
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(['report.view'], 'cashier'));
    }

    private function product(string $name, float $price): string
    {
        return $this->actingWithToken($this->token)->postJson('/api/v1/catalog/products', [
            'name' => $name, 'price' => $price, 'purchase_price' => $price / 2,
        ])->json('data.id');
    }

    private function orderPayload(string $productId, string $clientUuid): array
    {
        return [
            'business_id' => $this->businessId,
            'client_uuid' => $clientUuid,
            'action'      => 'complete',
            'items'       => [['product_id' => $productId, 'quantity' => 2, 'unit_price' => 3000]],
            'payments'    => [['amount' => 6000, 'method' => 'cash']],
        ];
    }

    public function test_resubmitting_same_client_uuid_does_not_create_duplicate_order(): void
    {
        $product = $this->product('Nasi Kucing', 3000);
        $clientUuid = (string) Str::uuid();

        $first = $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $this->orderPayload($product, $clientUuid));
        $first->assertCreated();

        $second = $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $this->orderPayload($product, $clientUuid));
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, SaleOrder::withoutGlobalScopes()
            ->where('business_id', $this->businessId)->count());
        // The duplicate submission must not double the payment either.
        $this->assertSame('6000.00', $second->json('data.paid_amount'));
    }

    public function test_different_client_uuids_create_separate_orders(): void
    {
        $product = $this->product('Sate Usus', 2000);

        $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $this->orderPayload($product, (string) Str::uuid()))
            ->assertCreated();
        $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $this->orderPayload($product, (string) Str::uuid()))
            ->assertCreated();

        $this->assertSame(2, SaleOrder::withoutGlobalScopes()
            ->where('business_id', $this->businessId)->count());
    }

    public function test_client_uuid_must_be_a_valid_uuid(): void
    {
        $product = $this->product('Tempe Bacem', 1500);

        $payload = $this->orderPayload($product, 'not-a-uuid');

        $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_uuid']);
    }

    public function test_orders_without_client_uuid_still_work(): void
    {
        $product = $this->product('Es Teh', 2500);

        $this->actingWithToken($this->token)->postJson('/api/v1/sales/orders', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 1, 'unit_price' => 2500]],
        ])->assertCreated();
    }

    public function test_sales_summary_aggregates_completed_orders(): void
    {
        $product = $this->product('Nasi Kucing', 3000);
        $this->actingWithToken($this->token)
            ->postJson('/api/v1/sales/orders', $this->orderPayload($product, (string) Str::uuid()))
            ->assertCreated();

        $response = $this->actingWithToken($this->token)->getJson('/api/v1/sales/summary');

        $response->assertOk()
            ->assertJsonPath('data.order_count', 1)
            ->assertJsonPath('data.gross_sales', 6000)
            ->assertJsonPath('data.average_ticket', 6000);
        $this->assertCount(1, $response->json('data.top_products'));
    }

    public function test_sales_summary_requires_report_view_permission(): void
    {
        $tokenWithout = $this->tokenFor($this->makeUser([], 'cashier'));

        $this->actingWithToken($tokenWithout)
            ->getJson('/api/v1/sales/summary')
            ->assertForbidden();
    }
}
