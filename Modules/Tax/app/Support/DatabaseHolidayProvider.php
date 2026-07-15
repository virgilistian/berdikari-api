<?php

namespace Modules\Tax\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Tax\Contracts\HolidayProviderInterface;
use Modules\Tax\Models\TaxHoliday;

/**
 * Default HolidayProviderInterface backed by the tax_holidays table, so an
 * admin can add/adjust a holiday without a code deploy. Swapping to a
 * config-file or future live-API implementation later only requires
 * rebinding the interface in TaxServiceProvider.
 */
class DatabaseHolidayProvider implements HolidayProviderInterface
{
    public function forYear(int $year): array
    {
        return Cache::remember("tax:holidays:{$year}", now()->addHours(6), function () use ($year) {
            return TaxHoliday::query()
                ->whereYear('date', $year)
                ->get()
                ->mapWithKeys(fn (TaxHoliday $holiday) => [$holiday->date->format('Y-m-d') => $holiday->name])
                ->all();
        });
    }

    public function isHoliday(Carbon $date): bool
    {
        return array_key_exists($date->format('Y-m-d'), $this->forYear($date->year));
    }

    public function nameFor(Carbon $date): ?string
    {
        return $this->forYear($date->year)[$date->format('Y-m-d')] ?? null;
    }
}
