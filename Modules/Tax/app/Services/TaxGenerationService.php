<?php

namespace Modules\Tax\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Tax\Contracts\HolidayProviderInterface;
use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;
use Modules\Tax\Models\TaxBusinessProfile;
use Modules\Tax\Models\TaxReport;
use Modules\Tax\Support\TaxGeneratorRegistry;

class TaxGenerationService
{
    public function __construct(
        private readonly CalendarService $calendarService,
        private readonly HolidayProviderInterface $holidayProvider,
        private readonly TaxGeneratorRegistry $registry,
        private readonly TaxNormalizationService $normalizationService,
    ) {
    }

    /**
     * Merge config('tax') defaults with the business profile's overrides.
     */
    public function resolveConfig(TaxBusinessProfile $profile): TaxGenerationConfig
    {
        $defaults = config('tax', []);
        $overrides = $profile->config_overrides ?? [];

        return new TaxGenerationConfig(array_replace_recursive($defaults, $overrides));
    }

    /**
     * Generate (or regenerate) a full month and upsert it as a draft TaxReport.
     */
    public function generate(TaxBusinessProfile $profile, int $month, int $year, string $generatedBy, ?int $seed = null): TaxReport
    {
        $generator = $this->registry->resolve($profile->business_type);
        $config = $this->resolveConfig($profile);
        $calendar = $this->calendarService->build($year, $month, $this->holidayProvider);

        $draft = $generator->generate($calendar, $config, $seed);
        $cap = $config->monthlyCap($calendar->hasHoliday());
        $draft = $this->normalizationService->normalize($generator, $draft, $cap, $config);

        $this->assertWeekendHolidaySalesValid($calendar, $draft, isSave: false);

        return DB::transaction(function () use ($profile, $month, $year, $generatedBy, $config, $calendar, $draft, $cap) {
            $report = TaxReport::query()->updateOrCreate(
                [
                    'business_id' => $profile->business_id,
                    'business_type' => $profile->business_type,
                    'period_month' => $month,
                    'period_year' => $year,
                ],
                [
                    'status' => 'draft',
                    'holiday_count_in_month' => $calendar->holidayCount,
                    'monthly_cap' => $cap,
                    'total_sales' => $draft->totalSales(),
                    'total_tax' => $draft->totalTax(),
                    'was_normalized' => $draft->wasNormalized,
                    'config_snapshot' => $config->toArray(),
                    'generated_by' => $generatedBy,
                    'generated_at' => now(),
                ],
            );

            $report->entries()->delete();
            $report->entries()->createMany($draft->entries);

            return $report->load('entries');
        });
    }

    /**
     * Apply manual edits, recompute derived totals, and persist — never
     * re-randomizes. Rejects (422) if a weekend/holiday day was edited down
     * to Rp0 sales (see assertWeekendHolidaySalesValid), or if the
     * recomputed total exceeds the cap.
     *
     * @param  array[]  $editedEntries  full or partial entries[] (the frontend
     *                                  always sends every day on save, not just
     *                                  the changed ones — so "edited" is detected
     *                                  by comparing values, not by presence)
     */
    public function recompute(TaxReport $report, array $editedEntries): TaxReport
    {
        $profile = TaxBusinessProfile::query()
            ->where('business_id', $report->business_id)
            ->where('business_type', $report->business_type)
            ->firstOrFail();

        $generator = $this->registry->resolve($report->business_type);
        $config = new TaxGenerationConfig($report->config_snapshot ?? config('tax', []));

        $currentEntries = $report->entries()->orderBy('day_number')->get()->keyBy('day_number');
        $editedByDay = collect($editedEntries)->keyBy('day_number');

        $entries = [];
        $editedDayNumbers = [];

        foreach ($currentEntries as $dayNumber => $entry) {
            $row = $entry->toArray();
            $wasEdited = (bool) $entry->is_manually_edited;

            if ($editedByDay->has($dayNumber)) {
                $patch = $editedByDay->get($dayNumber);
                foreach (['sales', 'ticket_qty', 'ticket_price'] as $field) {
                    if (! array_key_exists($field, $patch) || $patch[$field] === null) {
                        continue;
                    }

                    $incoming = (float) $patch[$field];
                    $current = $entry->$field !== null ? (float) $entry->$field : null;

                    if ($current === null || abs($incoming - $current) > 0.005) {
                        $wasEdited = true;
                    }

                    $row[$field] = $patch[$field];
                }
            }

            if ($wasEdited) {
                $editedDayNumbers[] = $dayNumber;
            }
            $entries[] = $row;
        }

        $draft = $generator->recompute($entries, $config);

        $calendar = $this->calendarService->build($report->period_year, $report->period_month, $this->holidayProvider);
        $this->assertWeekendHolidaySalesValid($calendar, $draft, isSave: true);

        $totalTax = $draft->totalTax();

        if ($totalTax > (float) $report->monthly_cap) {
            $excess = round($totalTax - (float) $report->monthly_cap, 2);

            throw ValidationException::withMessages([
                'entries' => "Total pajak bulan ini (Rp" . number_format($totalTax, 0, ',', '.') . ") melebihi batas Rp" . number_format((float) $report->monthly_cap, 0, ',', '.') . " sebesar Rp" . number_format($excess, 0, ',', '.') . ". Sesuaikan kembali nilai penjualan sebelum menyimpan.",
            ]);
        }

        return DB::transaction(function () use ($report, $draft, $editedDayNumbers) {
            foreach ($draft->entries as $row) {
                $dayNumber = $row['day_number'];
                $isEdited = in_array($dayNumber, $editedDayNumbers, true);

                // Only persist the derived/editable columns — $row also
                // carries read-only fields (id, timestamps, weekday_name, ...)
                // from the original toArray() that must not be mass-updated.
                $report->entries()->where('day_number', $dayNumber)->update([
                    'sales' => $row['sales'],
                    'tax' => $row['tax'],
                    'ticket_qty' => $row['ticket_qty'] ?? null,
                    'ticket_price' => $row['ticket_price'] ?? null,
                    'is_manually_edited' => $isEdited,
                ]);
            }

            $report->update([
                'total_sales' => $draft->totalSales(),
                'total_tax' => $draft->totalTax(),
            ]);

            return $report->fresh('entries');
        });
    }

    /**
     * Every Saturday, Sunday, or national holiday in the period must have
     * sales > 0 — a report cannot be generated or saved otherwise. The Eid
     * al-Fitr (Lebaran) period is excluded since businesses legitimately
     * have no sales for that whole stretch. Blocks the action (422) and
     * lists every offending date so the user knows what to fix. Runs both
     * at generation time and again on save (recompute), since manual edits
     * after generation can reintroduce a zero on one of these days.
     */
    private function assertWeekendHolidaySalesValid(CalendarContext $calendar, GeneratedReportDraft $draft, bool $isSave): void
    {
        $entriesByDay = collect($draft->entries)->keyBy('day_number');
        $violations = [];

        foreach ($calendar->days as $day) {
            if ($day->isEidAlFitri || (! $day->isWeekend && ! $day->isHoliday)) {
                continue;
            }

            $sales = (float) ($entriesByDay->get($day->dayNumber)['sales'] ?? 0);

            if ($sales <= 0.0) {
                $label = $day->isHoliday ? $day->holidayName : ucfirst($day->weekdayName);
                $violations[] = $day->date->format('d-m-Y') . " ({$label})";
            }
        }

        if (empty($violations)) {
            return;
        }

        $action = $isSave ? 'disimpan' : 'dibuat';
        $nextStep = $isSave
            ? 'Perbaiki nilai penjualan pada tanggal-tanggal tersebut agar lebih dari Rp0, lalu simpan kembali.'
            : 'Silakan buat ulang laporan untuk bulan ini.';

        throw ValidationException::withMessages([
            'period' => "Laporan pajak tidak bisa {$action} karena penjualan bernilai Rp0 ditemukan pada hari Sabtu, Minggu, atau libur nasional berikut: "
                . implode(', ', $violations)
                . ". Setiap akhir pekan dan hari libur nasional wajib memiliki nilai penjualan lebih dari Rp0 (periode libur Lebaran/Idul Fitri dikecualikan). {$nextStep}",
        ]);
    }
}
