<?php

namespace Modules\Tax\Generators\Concerns;

use Modules\Tax\DTO\DayInfo;
use Modules\Tax\DTO\TaxGenerationConfig;
use Random\Randomizer;

/**
 * Rolls a bounded random value for a non-zero day, biasing the range upward
 * on weekends/holidays, and bounded-retrying when the roll lands too close
 * to the previous day's value (avoids an obviously flat/repetitive pattern).
 */
trait AppliesWeekendHolidayBias
{
    protected function rollValue(DayInfo $day, TaxGenerationConfig $config, Randomizer $randomizer, ?float $previousValue, array $range): float
    {
        [$min, $max] = $range;

        $multiplier = 1.0;
        if ($day->isHoliday) {
            $multiplier = (float) $config->get(static::key() . '.holiday_multiplier', 1.0);
        } elseif ($day->isWeekend) {
            $multiplier = (float) $config->get(static::key() . '.weekend_multiplier', 1.0);
        }

        $biasedMin = (int) round($min * ($multiplier > 1.0 ? min($multiplier, 1.4) : 1.0));
        $biasedMax = (int) round(max($biasedMin + 1, $max * $multiplier));

        $variance = (float) $config->get(static::key() . '.min_day_variance', 0);
        $minGap = $variance > 0 ? (int) round($variance * $max) : 0;

        $value = $randomizer->getInt($biasedMin, $biasedMax);

        $attempts = 0;
        while ($minGap > 0 && $previousValue !== null && $previousValue > 0 && abs($value - $previousValue) < $minGap && $attempts < 5) {
            $value = $randomizer->getInt($biasedMin, $biasedMax);
            $attempts++;
        }

        return (float) $value;
    }
}
