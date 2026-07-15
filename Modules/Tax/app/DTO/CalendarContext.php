<?php

namespace Modules\Tax\DTO;

class CalendarContext
{
    /**
     * @param  DayInfo[]  $days
     */
    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $daysInMonth,
        public readonly array $days,
        public readonly int $holidayCount,
    ) {
    }

    public function hasHoliday(): bool
    {
        return $this->holidayCount > 0;
    }

    /**
     * Days grouped by ISO week (including partial edge weeks), preserving order.
     *
     * @return array<string, DayInfo[]>
     */
    public function weeks(): array
    {
        $weeks = [];

        foreach ($this->days as $day) {
            $weeks[$day->isoWeek()][] = $day;
        }

        return $weeks;
    }
}
