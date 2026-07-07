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
