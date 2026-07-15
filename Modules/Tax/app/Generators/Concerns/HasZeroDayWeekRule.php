<?php

namespace Modules\Tax\Generators\Concerns;

use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\TaxGenerationConfig;
use Random\Randomizer;

/**
 * Every ISO week gets at least one forced zero-value day; the random process
 * is allowed to additionally zero out up to `zero_days_max` days in a week,
 * so the result doesn't look mechanically "exactly one zero day every week."
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
            $dayNumbers = array_map(fn ($day) => $day->dayNumber, $weekDays);
            $count = min(count($dayNumbers), $randomizer->getInt($min, $max));
            $shuffled = $randomizer->shuffleArray($dayNumbers);
            array_push($zeroDays, ...array_slice($shuffled, 0, $count));
        }

        return $zeroDays;
    }
}
