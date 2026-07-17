<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * Regression coverage for the Stock-page vs Cashier-Shift-closing drift bug:
 * daily_stocks is scoped per business+date, not per shift, so closing the
 * first of several same-day shifts must not freeze it — otherwise later
 * sales/adjustments from other still-open shifts are silently dropped from
 * the stock ledger while the Sales module keeps recording them.
 */
class StockSyncAcrossShiftsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private const PERMS = [
        'pos.view', 'pos.open', 'pos.close',
        'inventory.view', 'inventory.create', 'inventory.update',
        'catalog.view', 'catalog.create',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
    }

    public function test_closing_one_of_several_same_day_shifts_does_not_freeze_stock_for_the_others(): void
    {
        $today = now()->toDateString();

        $ownerToken = $this->tokenFor($this->makeUser(self::PERMS, 'owner'));
        $product = $this->withToken($ownerToken)->postJson('/api/v1/catalog/products', [
            'name' => 'Nasi Kucing', 'price' => 3000, 'purchase_price' => 1500,
        ])->json('data.id');

        $this->withToken($ownerToken)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Nasi Kucing', 'opening_qty' => 30]],
        ])->assertCreated();

        $cashierA = $this->tokenFor($this->makeUser(self::PERMS, 'cashier'));
        $cashierB = $this->tokenFor($this->makeUser(self::PERMS, 'cashier'));

        // Both cashiers open concurrent shifts and sell against the same shared daily stock.
        $shiftA = $this->actingWithToken($cashierA)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 100000,
        ])->assertCreated()->json('data');

        $shiftB = $this->actingWithToken($cashierB)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 50000,
        ])->assertCreated()->json('data');

        $this->actingWithToken($cashierA)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 5, 'unit_price' => 3000]],
        ])->assertCreated();

        $this->actingWithToken($cashierB)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 3, 'unit_price' => 3000]],
        ])->assertCreated();

        // Cashier A closes first. Cashier B is still open, so daily stock must stay open.
        $closedA = $this->actingWithToken($cashierA)->postJson("/api/v1/sales/shifts/{$shiftA['id']}/close", [
            'closing_cash' => 100000 + (5 * 3000),
        ])->assertOk()->json('data');

        $this->assertCount(1, $closedA['stock_summary']);
        $this->assertSame(30, $closedA['stock_summary'][0]['opening_qty']);
        $this->assertSame(8, $closedA['stock_summary'][0]['sold_qty']); // 5 + 3, shared ledger
        $this->assertSame(22, $closedA['stock_summary'][0]['closing_qty']); // live snapshot, not yet frozen

        $this->assertDatabaseHas('daily_stocks', [
            'product_id' => $product, 'status' => 'open', 'sold_qty' => 8,
        ]);

        // Cashier B keeps selling after A's close — must still be recorded.
        $this->actingWithToken($cashierB)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 2, 'unit_price' => 3000]],
        ])->assertCreated();

        $stockPage = $this->actingWithToken($cashierB)->getJson(
            "/api/v1/inventory/daily-stock/{$today}?business_id={$this->businessId}"
        )->assertOk()->json('data.0');
        $this->assertSame('open', $stockPage['status']);
        $this->assertSame(10, $stockPage['sold_qty']);

        // Cashier B is now the last open shift — closing it finalizes the day.
        $closedB = $this->actingWithToken($cashierB)->postJson("/api/v1/sales/shifts/{$shiftB['id']}/close", [
            'closing_cash' => 50000 + (3 * 3000) + (2 * 3000),
        ])->assertOk()->json('data');

        $this->assertSame(10, $closedB['stock_summary'][0]['sold_qty']);
        $this->assertSame(20, $closedB['stock_summary'][0]['closing_qty']); // 30 - 10

        $this->assertDatabaseHas('daily_stocks', [
            'product_id' => $product, 'status' => 'closed', 'closing_qty' => 20,
        ]);

        // The Stock page (daily-stock detail) must show the exact same number
        // the shift closed with — the single source of truth requirement.
        $stockPageAfter = $this->actingWithToken($cashierB)->getJson(
            "/api/v1/inventory/daily-stock/{$today}?business_id={$this->businessId}"
        )->assertOk()->json('data.0');
        $this->assertSame('closed', $stockPageAfter['status']);
        $this->assertSame(20, $stockPageAfter['closing_qty']);

        // The realtime/valuation ledger (feeds the dashboard low-stock widget)
        // must be synced to the same authoritative closing count.
        $realtimeStock = $this->actingWithToken($cashierB)->getJson('/api/v1/inventory')
            ->assertOk()->json('data');
        $this->assertSame(20, collect($realtimeStock)->firstWhere('product_id', $product)['quantity']);
    }

    public function test_reopening_a_shift_the_same_day_after_close_keeps_stock_accurate(): void
    {
        $today = now()->toDateString();

        $ownerToken = $this->tokenFor($this->makeUser(self::PERMS, 'owner'));
        $product = $this->withToken($ownerToken)->postJson('/api/v1/catalog/products', [
            'name' => 'Es Teh', 'price' => 2000, 'purchase_price' => 1000,
        ])->json('data.id');

        $this->withToken($ownerToken)->postJson('/api/v1/inventory/daily-stock/open', [
            'business_id' => $this->businessId,
            'date'        => $today,
            'items'       => [['product_id' => $product, 'product_name' => 'Es Teh', 'opening_qty' => 40]],
        ])->assertCreated();

        $cashier = $this->tokenFor($this->makeUser(self::PERMS, 'cashier'));

        // First shift of the day: sell 10, then close (last/only open shift → freezes stock).
        $shift1 = $this->actingWithToken($cashier)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 0,
        ])->assertCreated()->json('data');

        $this->actingWithToken($cashier)->postJson('/api/v1/sales/checkout', [
            'business_id' => $this->businessId,
            'items'       => [['product_id' => $product, 'quantity' => 10, 'unit_price' => 2000]],
        ])->assertCreated();

        $closed1 = $this->actingWithToken($cashier)->postJson("/api/v1/sales/shifts/{$shift1['id']}/close", [
            'closing_cash' => 10 * 2000,
        ])->assertOk()->json('data');
        $this->assertSame(30, $closed1['stock_summary'][0]['closing_qty']);

        // Same cashier reopens for a second shift later the same day (e.g. after a break).
        // Stock is already closed for the date, so no reconciliation is expected — the
        // wizard's "no stock data" empty state — but the shift must still close cleanly.
        $shift2 = $this->actingWithToken($cashier)->postJson('/api/v1/sales/shifts/open', [
            'opening_cash' => 0,
        ])->assertCreated()->json('data');

        $closed2 = $this->actingWithToken($cashier)->postJson("/api/v1/sales/shifts/{$shift2['id']}/close", [
            'closing_cash' => 0,
        ])->assertOk()->json('data');

        $this->assertSame(0.0, (float) $closed2['total_sales']);
        $this->assertSame([], $closed2['stock_summary']);
    }
}
