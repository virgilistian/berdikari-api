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
        $this->token = $this->tokenFor($this->makeUser([], 'finance'));
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
        $futureDate = now()->addDay()->toDateString();

        $this->withToken($this->token)->postJson('/api/v1/finance', [
            'type' => 'expense', 'amount' => 20000, 'category' => 'Belanja Bahan', 'occurred_at' => $futureDate,
        ])->assertUnprocessable()->assertJsonValidationErrors(['occurred_at']);
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
}
