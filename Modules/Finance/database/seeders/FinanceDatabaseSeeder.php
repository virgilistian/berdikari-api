<?php

namespace Modules\Finance\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Models\FinanceCategory;

class FinanceDatabaseSeeder extends Seeder
{
    /**
     * Default pemasukan/pengeluaran categories every new business starts with.
     * Mirrors the fallback list in berdikari-web's finance store.
     *
     * @var array<string, string[]>
     */
    public const DEFAULT_CATEGORIES = [
        'expense' => [
            'Belanja Bahan',
            'Bayar Listrik',
            'Bayar Air',
            'Gaji Karyawan',
            'Perbaikan',
            'Transportasi',
            'BBM',
            'Sewa',
            'Perlengkapan',
            'Lainnya',
        ],
        'income' => [
            'Penjualan',
            'Jasa',
            'Pembayaran Piutang',
            'Investasi',
            'Hibah',
            'Lainnya',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * Seeds default categories for every existing business. Idempotent —
     * safe to re-run (skips categories that already exist).
     */
    public function run(): void
    {
        $businessIds = DB::table('businesses')->pluck('id');

        foreach ($businessIds as $businessId) {
            foreach (self::DEFAULT_CATEGORIES as $type => $names) {
                foreach ($names as $name) {
                    FinanceCategory::firstOrCreate([
                        'business_id' => $businessId,
                        'type'        => $type,
                        'name'        => $name,
                    ]);
                }
            }
        }
    }
}
