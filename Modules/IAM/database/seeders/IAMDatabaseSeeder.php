<?php

namespace Modules\IAM\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IAMDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo business (idempotent)
        $existingBusiness = DB::table('businesses')->first();

        if ($existingBusiness) {
            $businessId = $existingBusiness->id;
        } else {
            $businessId = (string) Str::uuid();
            DB::table('businesses')->insert([
                'id' => $businessId,
                'name' => 'Angkringan Berdikari',
                'tax_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create owner account
        User::firstOrCreate(
            ['email' => 'owner@berdikari.test'],
            [
                'business_id' => $businessId,
                'name' => 'Pemilik Demo',
                'role' => 'owner',
                'password' => bcrypt('password'),
            ]
        );

        // Create cashier account
        User::firstOrCreate(
            ['email' => 'kasir@berdikari.test'],
            [
                'business_id' => $businessId,
                'name' => 'Kasir Demo',
                'role' => 'cashier',
                'password' => bcrypt('password'),
            ]
        );

        $this->command->info('Demo akun berhasil dibuat:');
        $this->command->line('  Pemilik : owner@berdikari.test  / password');
        $this->command->line('  Kasir   : kasir@berdikari.test  / password');
    }
}
