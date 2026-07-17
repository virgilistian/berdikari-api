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
 *
 * The Idul Fitri (Lebaran) dates below are tagged `type: eid_al_fitr` — the
 * tax weekend/holiday zero-sales validation excludes this period, since
 * businesses legitimately have no sales for the whole cuti bersama stretch.
 * 2026 dates per SKB 3 Menteri No. 1497/2/5 Tahun 2025 (libur nasional 21-22
 * Maret, cuti bersama 20 & 23-24 Maret) — verify/update for other years.
 */
class TaxHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi', 'type' => TaxHoliday::TYPE_NATIONAL],
            ['date' => '2026-03-20', 'name' => 'Cuti Bersama Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR],
            ['date' => '2026-03-21', 'name' => 'Hari Raya Idul Fitri (Hari Pertama)', 'type' => TaxHoliday::TYPE_EID_AL_FITR],
            ['date' => '2026-03-22', 'name' => 'Hari Raya Idul Fitri (Hari Kedua)', 'type' => TaxHoliday::TYPE_EID_AL_FITR],
            ['date' => '2026-03-23', 'name' => 'Cuti Bersama Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR],
            ['date' => '2026-03-24', 'name' => 'Cuti Bersama Idul Fitri', 'type' => TaxHoliday::TYPE_EID_AL_FITR],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'type' => TaxHoliday::TYPE_NATIONAL],
            ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'type' => TaxHoliday::TYPE_NATIONAL],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia', 'type' => TaxHoliday::TYPE_NATIONAL],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'type' => TaxHoliday::TYPE_NATIONAL],
        ];

        foreach ($holidays as $holiday) {
            TaxHoliday::query()->updateOrCreate(
                ['date' => $holiday['date']],
                ['name' => $holiday['name'], 'type' => $holiday['type']],
            );
        }
    }
}
