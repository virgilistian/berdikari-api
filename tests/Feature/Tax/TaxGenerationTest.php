<?php

namespace Tests\Feature\Tax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Models\TaxHoliday;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TaxGenerationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        // RefreshDatabase resets the DB but not the cache — the app's real
        // CACHE_STORE (redis) persists across tests, which would otherwise
        // leak DatabaseHolidayProvider's per-year cache between test methods.
        Cache::flush();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(
            ['tax.view', 'tax.create', 'tax.update', 'tax.export', 'tax.manage'],
            'owner',
        ));

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

    public function test_generate_respects_monthly_cap_without_holiday(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 2, 'year' => 2027,
        ])->assertOk();

        $this->assertSame(0, $response->json('data.holiday_count_in_month'));
        $this->assertLessThanOrEqual(1_000_000, (float) $response->json('data.total_tax'));
        $this->assertCount(28, $response->json('data.entries')); // Feb 2027 is not a leap year
    }

    public function test_generate_respects_monthly_cap_with_holiday(): void
    {
        TaxHoliday::create(['date' => '2027-08-17', 'name' => 'Hari Kemerdekaan']);

        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 8, 'year' => 2027,
        ])->assertOk();

        $this->assertSame(1, $response->json('data.holiday_count_in_month'));
        $this->assertLessThanOrEqual(1_500_000, (float) $response->json('data.total_tax'));
    }

    public function test_swimming_pool_generation_accounts_for_leap_year_february(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'swimming_pool', 'month' => 2, 'year' => 2028,
        ])->assertOk();

        $this->assertCount(29, $response->json('data.entries')); // 2028 is a leap year
        $this->assertLessThanOrEqual(1_000_000, (float) $response->json('data.total_tax'));
    }

    public function test_generate_normalizes_when_over_cap(): void
    {
        TaxBusinessProfile::where('business_id', $this->businessId)
            ->where('business_type', 'restaurant')
            ->update([
                'config_overrides' => [
                    'restaurant' => ['sales_min' => 5_000_000, 'sales_max' => 6_000_000],
                ],
            ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 3, 'year' => 2027,
        ])->assertOk();

        $this->assertTrue((bool) $response->json('data.was_normalized'));
        $this->assertLessThanOrEqual(1_000_000, (float) $response->json('data.total_tax'));
    }

    public function test_manual_edit_over_cap_is_rejected(): void
    {
        $generate = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 4, 'year' => 2027,
        ])->assertOk();

        $reportId = $generate->json('data.id');
        $firstDay = $generate->json('data.entries.0.day_number');

        $this->withToken($this->token)->putJson("/api/v1/tax/reports/{$reportId}", [
            'entries' => [
                ['day_number' => $firstDay, 'sales' => 50_000_000],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['entries']);
    }

    public function test_manual_edit_recomputes_tax_and_totals(): void
    {
        $generate = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 4, 'year' => 2027,
        ])->assertOk();

        $reportId = $generate->json('data.id');
        $firstDay = $generate->json('data.entries.0.day_number');

        $response = $this->withToken($this->token)->putJson("/api/v1/tax/reports/{$reportId}", [
            'entries' => [
                ['day_number' => $firstDay, 'sales' => 100_000],
            ],
        ])->assertOk();

        $entries = collect($response->json('data.entries'));
        $edited = $entries->firstWhere('day_number', $firstDay);

        $this->assertSame(100_000.0, (float) $edited['sales']);
        $this->assertSame(10_000.0, (float) $edited['tax']);
        $this->assertTrue($edited['is_manually_edited']);
    }

    public function test_pdf_endpoint_returns_pdf_without_assets(): void
    {
        $generate = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 5, 'year' => 2027,
        ])->assertOk();

        $reportId = $generate->json('data.id');

        $this->withToken($this->token)->get("/api/v1/tax/reports/{$reportId}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_viewer_cannot_generate_or_upload_assets(): void
    {
        $viewerToken = $this->tokenFor($this->makeUser(['tax.view'], 'viewer'));

        $this->actingWithToken($viewerToken)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 6, 'year' => 2027,
        ])->assertForbidden();

        $this->actingWithToken($viewerToken)->postJson('/api/v1/tax/assets/signature', [])
            ->assertForbidden();
    }

    public function test_generate_fails_gracefully_when_profile_missing(): void
    {
        TaxBusinessProfile::where('business_id', $this->businessId)
            ->where('business_type', 'restaurant')
            ->delete();

        $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 7, 'year' => 2027,
        ])->assertStatus(422);
    }
}
