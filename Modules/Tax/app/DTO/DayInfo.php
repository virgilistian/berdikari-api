<?php

namespace Modules\Tax\DTO;

use Carbon\Carbon;

class DayInfo
{
    public function __construct(
        public readonly int $dayNumber,
        public readonly Carbon $date,
        public readonly string $weekdayName,
        public readonly bool $isWeekend,
        public readonly bool $isHoliday,
        public readonly ?string $holidayName,
        public readonly bool $isEidAlFitri = false,
    ) {
    }

    public function isoWeek(): string
    {
        return $this->date->format('o-W');
    }
}
