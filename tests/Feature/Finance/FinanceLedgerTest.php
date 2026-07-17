<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(['finance.view', 'finance.create', 'finance.update', 'finance.delete'], 'finance'));
    }

    public function test_can_record_manual_income_and_expense(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 100000, 'category' => 'Penjualan', 'note' => 'Kas awal',
        ])->assertCreated()->assertJsonPath('data.type', 'income');

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 40000, 'category' => 'Belanja Bahan',
        ])->assertCreated();

        $this->withToken($this->token)->getJson('/api/v1/finance')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_summary_returns_income_expense_and_net(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 100000, 'category' => 'Penjualan',
        ])->assertCreated();

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 30000, 'category' => 'Belanja Bahan',
        ])->assertCreated();

        $this->withToken($this->token)->getJson('/api/v1/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.total_income', 100000)
            ->assertJsonPath('data.total_expense', 30000)
            ->assertJsonPath('data.net', 70000);
    }

    public function test_validation_requires_type_amount_category(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/finance', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'amount', 'category']);
    }

    public function test_can_backdate_transaction_date(): void
    {
        $pastDate = now()->subDays(5)->toDateString();

        $response = $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 50000, 'category' => 'Penjualan', 'occurred_at' => $pastDate,
        ])->assertCreated();

        $entry = \Modules\Finance\Models\FinanceEntry::findOrFail($response->json('data.id'));
        $this->assertSame($pastDate, $entry->occurred_at->toDateString());
        $this->assertNotSame($pastDate, $entry->created_at->toDateString());
    }

    public function test_rejects_future_transaction_date(): void
    {
        $futureDate = now('Asia/Jakarta')->addDay()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 20000, 'category' => 'Belanja Bahan', 'occurred_at' => $futureDate,
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_accepts_todays_date_even_when_utc_and_jakarta_calendar_days_differ(): void
    {
        // 20:00 UTC is already past midnight WIB (UTC+7) — the exact window
        // where the server's UTC "today" used to lag a day behind the date
        // picker's browser-local "today", falsely rejecting today's entry.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-16 20:00:00', 'UTC'));

        $todayInJakarta = now('Asia/Jakarta')->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 15000, 'category' => 'Belanja Bahan', 'occurred_at' => $todayInJakarta,
        ])->assertCreated();

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_filters_and_summary_use_transaction_date_not_creation_date(): void
    {
        $pastDate = now()->subDays(3)->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 75000, 'category' => 'Penjualan', 'occurred_at' => $pastDate,
        ])->assertCreated();

        $this->withToken($this->token)->getJson('/api/v1/finance?from=' . now()->toDateString())
            ->assertOk()->assertJsonCount(0, 'data');

        $this->withToken($this->token)->getJson("/api/v1/finance?from={$pastDate}&to={$pastDate}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($this->token)->getJson("/api/v1/finance/summary?from={$pastDate}&to={$pastDate}")
            ->assertOk()
            ->assertJsonPath('data.total_income', 75000);
    }

    public function test_automatic_sale_income_cannot_be_deleted_manually(): void
    {
        $entry = \Modules\Finance\Models\FinanceEntry::create([
            'business_id' => $this->businessId,
            'type' => 'income', 'amount' => 5000, 'category' => 'Penjualan',
            'source_type' => 'sale_order', 'source_id' => (string) \Illuminate\Support\Str::uuid(),
            'occurred_at' => now(),
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/finance/{$entry->id}")
            ->assertStatus(422);
    }

    public function test_deleting_a_transaction_requires_finance_delete_permission(): void
    {
        $entry = \Modules\Finance\Models\FinanceEntry::create([
            'business_id' => $this->businessId,
            'type' => 'expense', 'amount' => 10000, 'category' => 'Belanja Bahan',
            'source_type' => 'manual', 'occurred_at' => now(),
        ]);

        $noPermToken = $this->tokenFor($this->makeUser(['finance.view'], 'finance'));

        $this->withToken($noPermToken)->deleteJson("/api/v1/finance/{$entry->id}")
            ->assertStatus(403);
    }

    public function test_deleting_a_transaction_soft_deletes_and_excludes_it_from_list_and_summary(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'income', 'amount' => 100000, 'category' => 'Penjualan',
        ])->assertCreated();

        $expense = $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 40000, 'category' => 'Belanja Bahan',
        ])->assertCreated()->json('data.id');

        $this->withToken($this->token)->deleteJson("/api/v1/finance/{$expense}")
            ->assertOk()->assertJsonPath('message', 'Transaksi berhasil dihapus.');

        $this->withToken($this->token)->getJson('/api/v1/finance')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($this->token)->getJson('/api/v1/finance/summary')
            ->assertOk()
            ->assertJsonPath('data.total_income', 100000)
            ->assertJsonPath('data.total_expense', 0)
            ->assertJsonPath('data.net', 100000);

        $this->assertSoftDeleted('finance_entries', ['id' => $expense]);
    }
}
