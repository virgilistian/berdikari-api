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
}
