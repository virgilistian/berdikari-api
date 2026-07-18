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
        $this->assertSame(5, $closed['total_items_sold']);
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

    public function test_active_shift_summary_reflects_completed_sales_before_closing(): void
    {
        $product = $this->product('Kopi', 5000);

        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 50000,
        ])->assertCreated()->json('data');

        // A freshly opened shift has no sales yet.
        $active = $this->withToken($this->token)->getJson('/api/v1/sales/shifts/active')
            ->assertOk()->json('data');
        $this->assertSame(0, $active['transaction_count']);
        $this->assertSame(0.0, (float) $active['total_sales']);

        // Two completed sales against the active shift (checkout defaults to a
        // full cash payment when 'paid'/'method' are omitted).
        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 5000]],
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 3, 'unit_price' => 5000]],
        ])->assertCreated();

        // An operational expense recorded mid-shift.
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 4000, 'category' => 'Lainnya',
            'note' => 'Parkir', 'shift_id' => $shift['id'],
        ])->assertCreated();

        // The shift is STILL OPEN — this is the "Tutup Shift" preview screen
        // reading GET /shifts/active before the cashier confirms closing.
        $active = $this->withToken($this->token)->getJson('/api/v1/sales/shifts/active')
            ->assertOk()->json('data');

        $this->assertSame(2, $active['transaction_count']);
        $this->assertSame(5, $active['total_items_sold']); // 2 + 3 units
        $this->assertSame(25000.0, (float) $active['total_sales']);
        $this->assertSame(4000.0, (float) $active['total_expenses']);
        $this->assertSame(21000.0, (float) $active['net_income']);
        $this->assertSame(25000.0, (float) $active['payment_breakdown']['cash']);

        // Closing must agree with what the preview already showed.
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 50000 + 25000,
        ])->assertOk()->json('data');

        $this->assertSame(2, $closed['transaction_count']);
        $this->assertSame(5, $closed['total_items_sold']);
        $this->assertSame(25000.0, (float) $closed['total_sales']);
        $this->assertSame(4000.0, (float) $closed['total_expenses']);
        $this->assertSame(21000.0, (float) $closed['net_income']);
    }

    public function test_expected_cash_matches_opening_plus_cash_sales_when_no_expenses(): void
    {
        $product = $this->product('Es Teh', 15000);

        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 10000,
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 5, 'unit_price' => 15000]],
        ])->assertCreated();

        // Opening 10.000 + cash sales 75.000 = expected 85.000; cashier counts
        // exactly 85.000 in the drawer, so the difference must be zero.
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 85000,
        ])->assertOk()->json('data');

        $this->assertSame(85000.0, (float) $closed['expected_cash']);
        $this->assertSame(0.0, (float) $closed['cash_difference']);
    }

    public function test_only_cash_payments_count_toward_expected_cash(): void
    {
        $product = $this->product('Nasi Kucing', 10000);

        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 20000,
        ])->assertCreated()->json('data');

        // Cash sale.
        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 10000]],
            'method'      => 'cash',
        ])->assertCreated();

        // QRIS and transfer sales must NOT inflate the cash expectation.
        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 3, 'unit_price' => 10000]],
            'method'      => 'qris',
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 1, 'unit_price' => 10000]],
            'method'      => 'transfer',
        ])->assertCreated();

        $active = $this->withToken($this->token)->getJson('/api/v1/sales/shifts/active')
            ->assertOk()->json('data');
        $this->assertSame(60000.0, (float) $active['total_sales']);
        $this->assertSame(6, $active['total_items_sold']); // 2 + 3 + 1 units
        $this->assertSame(20000.0, (float) $active['payment_breakdown']['cash']);
        $this->assertSame(30000.0, (float) $active['payment_breakdown']['qris']);
        $this->assertSame(10000.0, (float) $active['payment_breakdown']['transfer']);

        // Expected cash: opening 20.000 + cash sales 20.000 = 40.000 — QRIS (30.000)
        // and transfer (10.000) are excluded.
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 40000,
        ])->assertOk()->json('data');

        $this->assertSame(40000.0, (float) $closed['expected_cash']);
        $this->assertSame(0.0, (float) $closed['cash_difference']);
    }

    public function test_shift_expenses_reduce_expected_cash(): void
    {
        $product = $this->product('Kopi', 10000);

        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 50000,
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 5, 'unit_price' => 10000]],
        ])->assertCreated();

        // 20.000 paid out of the cash drawer for an operational expense.
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 20000, 'category' => 'Transportasi',
            'note' => 'Ojek antar pesanan', 'shift_id' => $shift['id'],
        ])->assertCreated();

        // Expected cash: opening 50.000 + cash sales 50.000 - expenses 20.000 = 80.000.
        // Cashier counts exactly 80.000 — the drawer is short by the expense paid out,
        // and that shortfall must NOT show up as an unexplained cash difference.
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 80000,
        ])->assertOk()->json('data');

        $this->assertSame(80000.0, (float) $closed['expected_cash']);
        $this->assertSame(0.0, (float) $closed['cash_difference']);
    }

    public function test_cash_difference_is_negative_on_shortage_and_positive_on_excess(): void
    {
        $product = $this->product('Teh Manis', 5000);

        $shift = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 30000,
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 4, 'unit_price' => 5000]],
        ])->assertCreated();

        // Expected cash: 30.000 + 20.000 = 50.000. Cashier counts only 45.000 (shortage).
        $closed = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift['id']}/close", [
            'closing_cash' => 45000,
        ])->assertOk()->json('data');

        $this->assertSame(50000.0, (float) $closed['expected_cash']);
        $this->assertSame(-5000.0, (float) $closed['cash_difference']);

        // Second shift, same drawer: cashier counts more than expected (excess).
        $shift2 = $this->withToken($this->token)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 30000,
        ])->assertCreated()->json('data');

        $this->withToken($this->token)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 4, 'unit_price' => 5000]],
        ])->assertCreated();

        // Expected cash: 30.000 + 20.000 = 50.000. Cashier counts 55.000 (excess).
        $closed2 = $this->withToken($this->token)->postJson("/api/v1/sales/shifts/{$shift2['id']}/close", [
            'closing_cash' => 55000,
        ])->assertOk()->json('data');

        $this->assertSame(50000.0, (float) $closed2['expected_cash']);
        $this->assertSame(5000.0, (float) $closed2['cash_difference']);
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
