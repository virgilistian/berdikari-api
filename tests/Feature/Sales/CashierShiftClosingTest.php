<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Closing a shift now folds in the daily-stock reconciliation (opening/sold/
 * adjusted/remaining) and the operational expenses recorded during the shift,
 * so the shift summary carries everything without a separate "close stock" step.
 */
class CashierShiftClosingTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(
            ['pos.view', 'pos.open', 'pos.close', 'pos.expense', 'inventory.view', 'inventory.create', 'inventory.update', 'catalog.view', 'catalog.create'],
            'cashier'
        ));
    }

    private function product(string $name, float $price): string
    {
        return $this->withToken($this->token)->postJson('/api/v1/catalog/products', [
            'name' => $name, 'price' => $price, 'purchase_price' => $price / 2,
        ])->json('data.id');
    }

    public function test_close_shift_reconciles_stock_and_deducts_expenses_from_net_income(): void
    {
        $today = now()->toDateString();
        $product = $this->product('Es Teh', 3000);

        // Open the shift.
        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 100000,
        ])->assertCreated()->json('data');

        // Kitchen input: 20 units on hand for the day.
        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Es Teh', 'opening_qty' => 20]],
        ])->assertCreated();

        // Sell 5.
        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 5, 'unit_price' => 3000]],
        ])->assertCreated();

        // Physical count is 2 less than system remaining (20 - 5 = 15) — cashier adjusts with a reason.
        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/adjust', [
            'business_id'     => $this->businessId,
            'date'            => $today,
            'product_id'      => $product,
            'adjustment_qty'  => -2,
            'adjustment_note' => 'Gelas pecah',
        ])->assertOk();

        // Adjustment reason is required once qty changes.
        $this->withToken($this->token)->postJson('/api/v1/inventory/daily-stock/adjust', [
            'business_id'    => $this->businessId,
            'date'           => $today,
            'product_id'     => $product,
            'adjustment_qty' => -1,
        ])->assertStatus(422)->assertJsonValidationErrors(['adjustment_note']);

        // Cashier records an operational expense against the active shift.
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type'     => 'expense',
            'amount'   => 15000,
            'category' => 'Transportasi',
            'note'     => 'Ojek antar pesanan',
            'shift_id' => $shift['id'],
        ])->assertCreated()->assertJsonPath('data.source_type', 'shift_expense');

        // Close the shift: reconciliation + expenses fold into the summary.
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 100000 + 15000, // opening + cash sales (5 * 3000)
        ])->assertOk()->json('data');

        $this->assertSame(15000.0, (float) $closed['total_sales']);
        $this->assertSame(15000.0, (float) $closed['total_expenses']);
        $this->assertSame(0.0, (float) $closed['net_income']); // 15000 sales - 15000 expenses

        $this->assertCount(1, $closed['stock_summary']);
        $stock = $closed['stock_summary'][0];
        $this->assertSame(20, $stock['opening_qty']);
        $this->assertSame(5, $stock['sold_qty']);
        $this->assertSame(-2, $stock['adjustment_qty']);
        $this->assertSame('Gelas pecah', $stock['adjustment_note']);
        $this->assertSame(13, $stock['closing_qty']); // 20 - 5 - 2

        $this->assertDatabaseHas('daily_stocks', [
            'product_id' => $product, 'status' => 'closed', 'closing_qty' => 13,
        ]);
    }

    public function test_expense_creation_requires_pos_expense_permission(): void
    {
        $noPermToken = $this->tokenFor($this->makeUser(['pos.open'], 'cashier'));

        $shift = $this->withToken($noPermToken)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 50000,
        ])->assertCreated()->json('data');

        $this->withToken($noPermToken)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 5000, 'category' => 'Lainnya', 'shift_id' => $shift['id'],
        ])->assertForbidden();
    }
}
