<?php

namespace Tests\Feature\Tax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Models\TaxHoliday;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

/**
 * A generated report must never carry Rp0 sales on a Saturday, Sunday, or
 * national holiday — except during the Eid al-Fitr (Lebaran) period, where
 * a zero day is expected. See TaxGenerationService::assertWeekendHolidaySalesValid().
 */
class TaxWeekendHolidaySalesValidationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(['tax.view', 'tax.create'], 'owner'));

        TaxBusinessProfile::create([
            'business_id' => $this->businessId,
            'business_type' => 'restaurant',
            'npwpd' => '03001295329004',
            'company_name' => 'Rm.Test',
        ]);

        TaxBusinessProfile::create([
            'business_id' => $this->businessId,
            'business_type' => 'swimming_pool',
            'npwpd' => '04001295229004',
            'company_name' => 'Kolam Test',
        ]);
    }

    public function test_generated_restaurant_report_never_has_zero_sales_on_weekend_or_holiday(): void
    {
        // March 2027 has ordinary weekends and no seeded holiday — the forced
        // zero-day picker is the only thing that could put a zero on one.
        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 3, 'year' => 2027,
        ])->assertOk();

        foreach ($response->json('data.entries') as $entry) {
            if ($entry['is_weekend'] || $entry['is_holiday']) {
                $this->assertGreaterThan(
                    0,
                    (float) $entry['sales'],
                    "Day {$entry['day_number']} ({$entry['weekday_name']}) is weekend/holiday with zero sales",
                );
            }
        }
    }

    public function test_generation_allows_zero_sales_during_eid_al_fitr_period(): void
    {
        TaxHoliday::create(['date' => '2027-03-20', 'name' => 'Cuti Bersama Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR]);
        TaxHoliday::create(['date' => '2027-03-21', 'name' => 'Hari Raya Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR]);

        $profile = TaxBusinessProfile::where('business_id', $this->businessId)
            ->where('business_type', 'restaurant')
            ->first();

        // Seed 1 is known (empirically) to land day 20 — a seeded Eid day —
        // on zero sales via the forced zero-day-per-week pick. Generation
        // must still succeed: an Eid day is allowed to be zero even though
        // it falls on a Saturday.
        $service = app(\Modules\Tax\Services\TaxGenerationService::class);
        $report = $service->generate($profile, month: 3, year: 2027, generatedBy: 'test', seed: 1);

        $day20 = $report->entries->firstWhere('day_number', 20);
        $this->assertSame(0.0, (float) $day20->sales);
    }

    public function test_generation_is_blocked_with_a_clear_message_when_a_weekend_has_zero_sales(): void
    {
        // swimming_pool's qty_min defaults to 0, so pinning qty_max to 0 too
        // guarantees every day (including weekends) rolls to zero sales,
        // regardless of the random seed.
        TaxBusinessProfile::where('business_id', $this->businessId)
            ->where('business_type', 'swimming_pool')
            ->update(['config_overrides' => ['swimming_pool' => ['qty_min' => 0, 'qty_max' => 0]]]);

        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'swimming_pool', 'month' => 3, 'year' => 2027,
        ])->assertStatus(422)->assertJsonValidationErrors(['period']);

        $message = $response->json('errors.period.0');
        $this->assertStringContainsString('tidak bisa dibuat', $message);
        $this->assertStringContainsString('06-03-2027', $message); // a Saturday in March 2027
    }

    public function test_saving_a_report_is_blocked_when_a_manual_edit_zeroes_out_a_weekend_day(): void
    {
        $this->token = $this->tokenFor($this->makeUser(['tax.view', 'tax.create', 'tax.update'], 'owner'));

        $generated = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 3, 'year' => 2027,
        ])->assertOk();

        $reportId = $generated->json('data.id');
        $entries = collect($generated->json('data.entries'))
            ->map(fn (array $entry) => [
                'day_number' => $entry['day_number'],
                // 6 March 2027 is a Saturday — force it to zero to simulate
                // a user editing a weekend day's sales down after generation.
                'sales' => $entry['day_number'] === 6 ? 0 : $entry['sales'],
            ])
            ->all();

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/tax/reports/{$reportId}", ['entries' => $entries])
            ->assertStatus(422)->assertJsonValidationErrors(['period']);

        $message = $response->json('errors.period.0');
        $this->assertStringContainsString('tidak bisa disimpan', $message);
        $this->assertStringContainsString('06-03-2027', $message);
    }

    public function test_saving_a_report_still_allows_zero_sales_during_eid_al_fitr_period(): void
    {
        TaxHoliday::create(['date' => '2027-03-20', 'name' => 'Cuti Bersama Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR]);
        TaxHoliday::create(['date' => '2027-03-21', 'name' => 'Hari Raya Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR]);

        $this->token = $this->tokenFor($this->makeUser(['tax.view', 'tax.create', 'tax.update'], 'owner'));

        $generated = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 3, 'year' => 2027,
        ])->assertOk();

        $reportId = $generated->json('data.id');
        $entries = collect($generated->json('data.entries'))
            ->map(fn (array $entry) => [
                'day_number' => $entry['day_number'],
                'sales' => $entry['day_number'] === 20 ? 0 : $entry['sales'],
            ])
            ->all();

        $this->withToken($this->token)
            ->putJson("/api/v1/tax/reports/{$reportId}", ['entries' => $entries])
            ->assertOk();
    }
}
