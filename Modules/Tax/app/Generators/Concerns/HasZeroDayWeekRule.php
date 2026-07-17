<?php

namespace Modules\Tax\Generators\Concerns;

use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\TaxGenerationConfig;
use Random\Randomizer;

/**
 * Every ISO week gets at least one forced zero-value day; the random process
 * is allowed to additionally zero out up to `zero_days_max` days in a week,
 * so the result doesn't look mechanically "exactly one zero day every week."
 *
 * Candidates exclude Saturdays, Sundays, and national holidays — a report
 * must never show Rp0 sales on those days (see TaxGenerationService's
 * weekend/holiday validation) — except during the Eid al-Fitr (Lebaran)
 * period, where a zero day is expected and allowed.
 */
trait HasZeroDayWeekRule
{
    /**
     * @return int[] day numbers forced to zero
     */
    protected function pickZeroDays(CalendarContext $calendar, TaxGenerationConfig $config, Randomizer $randomizer): array
    {
        $min = max(1, (int) $config->get(static::key() . '.zero_days_min', 1));
        $max = max($min, (int) $config->get(static::key() . '.zero_days_max', 3));

        $zeroDays = [];

        foreach ($calendar->weeks() as $weekDays) {
            $candidates = array_values(array_filter(
                $weekDays,
                fn ($day) => $day->isEidAlFitri || (! $day->isWeekend && ! $day->isHoliday),
            ));

            if (empty($candidates)) {
                continue;
            }

            $dayNumbers = array_map(fn ($day) => $day->dayNumber, $candidates);
            $count = min(count($dayNumbers), $randomizer->getInt($min, $max));
            $shuffled = $randomizer->shuffleArray($dayNumbers);
            array_push($zeroDays, ...array_slice($shuffled, 0, $count));
        }

        return $zeroDays;
    }
}
