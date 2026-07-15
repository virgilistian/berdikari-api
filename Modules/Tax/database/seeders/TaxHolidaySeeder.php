<?php

namespace Modules\Tax\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tax\Models\TaxHoliday;

/**
 * Initial Indonesian national holiday data. Fixed-date holidays (New Year,
 * Labor Day, Independence Day, Christmas) recur every year; lunar/Hijri-based
 * holidays (Idul Fitri, Idul Adha, Isra Mikraj, Nyepi, Waisak, etc.) shift
 * each year and MUST be reviewed/updated annually by an admin — this seeder
 * is a starting point, not a source of permanent truth.
 */
class TaxHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi'],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional'],
            ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila'],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia'],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'],
        ];

        foreach ($holidays as $holiday) {
            TaxHoliday::query()->updateOrCreate(
                ['date' => $holiday['date']],
                ['name' => $holiday['name']],
            );
        }
    }
}
