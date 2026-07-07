<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser([], 'cashier'));
    }

    private function product(string $name, float $price): string
    {
        return $this->withToken($this->token)->postJson('/api/v1/catalog/products', [
            'name' => $name, 'price' => $price, 'purchase_price' => $price / 2,
        ])->json('data.id');
    }

    private function stockUp(string $productId, int $qty): void
    {
        $this->withToken($this->token)->postJson('/api/v1/inventory/receive', [
            'product_id' => $productId, 'quantity' => $qty,
        ])->assertCreated();
    }

    private function quantityOf(string $productId): int
    {
        return (int) $this->withToken($this->token)
            ->getJson('/api/v1/inventory')
            ->json('data.0.quantity');
    }

    public function test_checkout_completes_order_deducts_stock_and_records_income(): void
    {
        $product = $this->product('Nasi Kucing', 3000);
        $this->stockUp($product, 20);

        $response = $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items' => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 3000]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.status', 'completed')
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.total_amount', '6000.00')
            ->assertJsonPath('order.paid_amount', '6000.00');

        // Inventory deducted 20 -> 18
        $this->assertSame(18, $this->quantityOf($product));

        // Finance income auto-recorded
        $this->assertDatabaseHas('finance_entries', [
            'type' => 'income', 'category' => 'Penjualan', 'amount' => 6000,
            'source_type' => 'sale_order',
        ]);
    }

    public function test_hold_order_saves_without_deducting_stock_or_income(): void
    {
        $product = $this->product('Sate Usus', 2000);
        $this->stockUp($product, 10);

        $order = $this->withToken($this->token)->postJson('/api/v1/sales/orders', [
            'business_id' => $this->businessId,
            'action' => 'hold',
            'items' => [['product_id' => $product, 'quantity' => 3, 'unit_price' => 2000]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->json('data');

        // No deduction while held
        $this->assertSame(10, $this->quantityOf($product));
        $this->assertDatabaseCount('finance_entries', 0);

        // Completing the held order deducts stock
        $this->withToken($this->token)->postJson("/api/v1/sales/orders/{$order['id']}/complete", [
            'payments' => [['amount' => 6000]],
        ])->assertOk()->assertJsonPath('data.payment_status', 'paid');

        $this->assertSame(7, $this->quantityOf($product));
        $this->assertDatabaseHas('finance_entries', ['type' => 'income', 'amount' => 6000]);
    }

    public function test_pay_later_then_partial_then_settle(): void
    {
        $product = $this->product('Kopi', 5000);
        $this->stockUp($product, 10);

        // Pay later: completed but unpaid
        $order = $this->withToken($this->token)->postJson('/api/v1/sales/orders', [
            'business_id' => $this->businessId,
            'action' => 'complete',
            'items' => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 5000]],
        ])->assertCreated()
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->json('data');

        $this->assertSame(8, $this->quantityOf($product)); // stock out on completion
        $this->assertDatabaseCount('finance_entries', 0);  // no cash yet

        // Partial payment 4000
        $this->withToken($this->token)->postJson("/api/v1/sales/orders/{$order['id']}/payments", [
            'amount' => 4000,
        ])->assertOk()->assertJsonPath('data.payment_status', 'partial');

        $this->assertDatabaseHas('finance_entries', ['type' => 'income', 'amount' => 4000]);

        // Settle remaining 6000
        $this->withToken($this->token)->postJson("/api/v1/sales/orders/{$order['id']}/payments", [
            'amount' => 6000,
        ])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.balance_due', 0);

        // Total income = 10000
        $this->assertSame(10000.0, (float) \Modules\Finance\Models\FinanceEntry::where('type', 'income')->sum('amount'));
    }

    public function test_partial_change_is_computed_on_overpay(): void
    {
        $product = $this->product('Teh', 3000);
        $this->stockUp($product, 10);

        $order = $this->withToken($this->token)->postJson('/api/v1/sales/orders', [
            'business_id' => $this->businessId,
            'action' => 'complete',
            'items' => [['product_id' => $product, 'quantity' => 1, 'unit_price' => 3000]],
            'payments' => [['amount' => 5000]], // pays 3000, change 2000
        ])->assertCreated()->json('data');

        $this->assertEquals('3000.00', $order['paid_amount']);
        $this->assertEquals('2000.00', $order['change_amount']);
        $this->assertEquals('paid', $order['payment_status']);

        // Income recognised only for the 3000 applied, not the tendered 5000
        $this->assertDatabaseHas('finance_entries', ['type' => 'income', 'amount' => 3000]);
    }

    public function test_refund_restores_stock_and_records_expense(): void
    {
        $product = $this->product('Susu', 5000);
        $this->stockUp($product, 10);

        $order = $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items' => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 5000]],
        ])->json('order');

        $this->assertSame(8, $this->quantityOf($product));

        $this->withToken($this->token)->postJson("/api/v1/sales/orders/{$order['id']}/refund")
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.payment_status', 'refunded');

        // Stock restored 8 -> 10
        $this->assertSame(10, $this->quantityOf($product));

        // Refund recorded as expense
        $this->assertDatabaseHas('finance_entries', [
            'type' => 'expense', 'category' => 'Refund Penjualan', 'amount' => 10000,
        ]);
    }

    public function test_cancel_held_order(): void
    {
        $product = $this->product('Gorengan', 1000);

        $order = $this->withToken($this->token)->postJson('/api/v1/sales/orders', [
            'business_id' => $this->businessId,
            'action' => 'hold',
            'items' => [['product_id' => $product, 'quantity' => 5, 'unit_price' => 1000]],
        ])->json('data');

        $this->withToken($this->token)->postJson("/api/v1/sales/orders/{$order['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_orders_can_be_listed_and_filtered_by_status(): void
    {
        $product = $this->product('Roti', 2000);
        $this->stockUp($product, 10);

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items' => [['product_id' => $product, 'quantity' => 1, 'unit_price' => 2000]],
        ])->assertCreated();

        $this->withToken($this->token)->getJson('/api/v1/sales/orders?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }
}
