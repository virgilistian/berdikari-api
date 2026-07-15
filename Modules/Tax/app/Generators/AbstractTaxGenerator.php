<?php

namespace Modules\Tax\Generators;

use Modules\Tax\Contracts\TaxGeneratorInterface;
use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\DayInfo;
use Modules\Tax\DTO\GeneratedReportDraft;
use Modules\Tax\DTO\TaxGenerationConfig;
use Modules\Tax\Generators\Concerns\AppliesWeekendHolidayBias;
use Modules\Tax\Generators\Concerns\HasZeroDayWeekRule;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

abstract class AbstractTaxGenerator implements TaxGeneratorInterface
{
    use HasZeroDayWeekRule, AppliesWeekendHolidayBias;

    /**
     * [min, max] bounds for the randomized "driving value" (Sales for
     * restaurant, ticket quantity for swimming pool).
     */
    abstract protected function driveValueRange(TaxGenerationConfig $config): array;

    /**
     * The entries[] column that TaxNormalizationService scales down.
     */
    abstract public function drivingValueKey(): string;

    public function drivingValueStep(): int
    {
        return 1;
    }

    /**
     * Build a full entry row from a rolled (or zeroed) driving value.
     */
    abstract protected function buildEntryFromValue(DayInfo $day, float $value, TaxGenerationConfig $config): array;

    public function generate(CalendarContext $calendar, TaxGenerationConfig $config, ?int $seed = null): GeneratedReportDraft
    {
        $randomizer = new Randomizer($seed !== null ? new Mt19937($seed) : new Secure());
        $range = $this->driveValueRange($config);
        $zeroDays = $this->pickZeroDays($calendar, $config, $randomizer);

        $entries = [];
        $previousValue = null;

        foreach ($calendar->days as $day) {
            if (in_array($day->dayNumber, $zeroDays, true)) {
                $value = 0.0;
            } else {
                $value = $this->rollValue($day, $config, $randomizer, $previousValue, $range);
            }

            $previousValue = $value;
            $entries[] = $this->buildEntryFromValue($day, $value, $config);
        }

        return new GeneratedReportDraft($entries);
    }
}
