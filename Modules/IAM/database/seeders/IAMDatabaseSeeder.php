<?php

namespace Modules\IAM\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IAMDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Business ──────────────────────────────────────────────────────
        $existingBusiness = DB::table('businesses')->first();

        if ($existingBusiness) {
            $businessId = $existingBusiness->id;
        } else {
            $businessId = (string) Str::uuid();
            DB::table('businesses')->insert([
                'id'         => $businessId,
                'name'       => 'Angkringan Berdikari',
                'tax_id'     => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── 2. Seed permissions + roles ──────────────────────────────────────
        $this->call([
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // ── 3. Demo users (one per role) ─────────────────────────────────────
        $this->call([
            UserByRoleSeeder::class,
        ]);
    }
}
