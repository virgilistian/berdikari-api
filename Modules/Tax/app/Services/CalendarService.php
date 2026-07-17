<?php

namespace Modules\Tax\Services;

use Carbon\Carbon;
use Modules\Tax\Contracts\HolidayProviderInterface;
use Modules\Tax\DTO\CalendarContext;
use Modules\Tax\DTO\DayInfo;

class CalendarService
{
    /**
     * Lowercase Indonesian weekday names, matching the existing Excel
     * template ("kamis", "jumat", ...). Keyed by Carbon::dayOfWeekIso (1=Mon..7=Sun).
     */
    private const WEEKDAY_NAMES = [
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
        7 => 'minggu',
    ];

    public function build(int $year, int $month, HolidayProviderInterface $holidays): CalendarContext
    {
        $firstOfMonth = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $daysInMonth = $firstOfMonth->daysInMonth; // leap-year aware via Carbon/PHP DateTime

        $days = [];
        $holidayCount = 0;

        for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++) {
            $date = $firstOfMonth->copy()->setDay($dayNumber);
            $isWeekend = in_array($date->dayOfWeekIso, [6, 7], true);
            $isHoliday = $holidays->isHoliday($date);
            $holidayName = $isHoliday ? $holidays->nameFor($date) : null;
            $isEidAlFitri = $holidays->isEidAlFitri($date);

            if ($isHoliday) {
                $holidayCount++;
            }

            $days[] = new DayInfo(
                dayNumber: $dayNumber,
                date: $date,
                weekdayName: self::WEEKDAY_NAMES[$date->dayOfWeekIso],
                isWeekend: $isWeekend,
                isHoliday: $isHoliday,
                holidayName: $holidayName,
                isEidAlFitri: $isEidAlFitri,
            );
        }

        return new CalendarContext(
            year: $year,
            month: $month,
            daysInMonth: $daysInMonth,
            days: $days,
            holidayCount: $holidayCount,
        );
    }
}
