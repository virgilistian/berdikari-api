<?php

namespace Modules\Tax\Contracts;

use Carbon\Carbon;

interface HolidayProviderInterface
{
    /**
     * @return array<string, string> map of 'Y-m-d' => holiday name
     */
    public function forYear(int $year): array;

    public function isHoliday(Carbon $date): bool;

    public function nameFor(Carbon $date): ?string;

    /**
     * Whether the date falls within the Eid al-Fitr (Lebaran) holiday
     * period — excluded from the weekend/holiday zero-sales validation rule.
     */
    public function isEidAlFitri(Carbon $date): bool;
}
