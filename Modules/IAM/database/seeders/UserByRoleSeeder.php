<?php

namespace Modules\IAM\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds one demo user per Berdikari role.
 * Every user shares password "password".
 *
 * Run standalone:
 *   docker compose exec api php artisan db:seed --class="Modules\IAM\Database\Seeders\UserByRoleSeeder"
 */
class UserByRoleSeeder extends Seeder
{
    /**
     * spatie-role => [email, name (Bahasa Indonesia), legacy role column]
     *
     * Email convention: <domain-term>@berdikari.test using plain Bahasa terms
     * so credentials are memorable during testing.
     *
     * NOTE: `super-admin` is intentionally excluded — it is a system-wide role
     * with null team_id, which conflicts with the NOT NULL constraint on
     * model_has_roles.business_id. super-admin accounts are provisioned by
     * platform operators outside this seeder.
     */
    private const USERS = [
        'business-owner' => [
            'email' => 'owner@berdikari.test',
            'name'  => 'Pemilik Usaha Demo',
            'role'  => 'owner',
        ],
        'manager' => [
            'email' => 'manager@berdikari.test',
            'name'  => 'Manajer Demo',
            'role'  => 'manager',
        ],
        'supervisor' => [
            'email' => 'supervisor@berdikari.test',
            'name'  => 'Supervisor Demo',
            'role'  => 'supervisor',
        ],
        'cashier' => [
            'email' => 'kasir@berdikari.test',
            'name'  => 'Kasir Demo',
            'role'  => 'cashier',
        ],
        'kitchen-staff' => [
            'email' => 'dapur@berdikari.test',
            'name'  => 'Staf Dapur Demo',
            'role'  => 'kitchen-staff',
        ],
        'inventory-staff' => [
            'email' => 'stok@berdikari.test',
            'name'  => 'Staf Inventori Demo',
            'role'  => 'inventory-staff',
        ],
        'finance' => [
            'email' => 'keuangan@berdikari.test',
            'name'  => 'Staf Keuangan Demo',
            'role'  => 'finance',
        ],
        'employee' => [
            'email' => 'karyawan@berdikari.test',
            'name'  => 'Karyawan Demo',
            'role'  => 'employee',
        ],
        'viewer' => [
            'email' => 'peninjau@berdikari.test',
            'name'  => 'Peninjau Demo',
            'role'  => 'viewer',
        ],
    ];

    public function run(): void
    {
        $businessId = DB::table('businesses')->value('id');

        $this->command->info('');
        $this->command->info('Akun demo per role (semua password: "password"):');
        $this->command->line(str_repeat('─', 72));
        $this->command->line(
            str_pad('Email', 34) .
            str_pad('Role', 20) .
            'Menu Sidebar'
        );
        $this->command->line(str_repeat('─', 72));

        foreach (self::USERS as $roleName => $meta) {
            // All demo roles are business-scoped
            setPermissionsTeamId($businessId);

            $user = User::firstOrCreate(
                ['email' => $meta['email']],
                [
                    'name'        => $meta['name'],
                    'role'        => $meta['role'],   // legacy column
                    'business_id' => $businessId,
                    'password'    => bcrypt('password'),
                ]
            );

            // syncRoles replaces any previously assigned roles so re-seeding is idempotent
            $user->syncRoles([$roleName]);

            $this->command->line(
                str_pad($meta['email'], 34) .
                str_pad($roleName, 20) .
                $this->menuSummary($roleName)
            );
        }

        $this->command->line(str_repeat('─', 72));
        $this->command->info('');
    }

    /**
     * Returns a short human-readable summary of which sidebar menus the role sees.
     * Derived from nav.ts permissions — kept in sync manually.
     */
    private function menuSummary(string $role): string
    {
        $map = [
            'business-owner' => 'Semua menu',
            'manager'        => 'Beranda, Kasir, Keuangan, Katalog, Stok, Laporan, Karyawan, Pengaturan',
            'supervisor'     => 'Beranda, Kasir, Keuangan, Katalog, Stok, Laporan, Karyawan',
            'cashier'        => 'Beranda, Kasir, Katalog, Stok',
            'kitchen-staff'  => 'Beranda, Katalog, Stok',
            'inventory-staff'=> 'Beranda, Katalog, Stok',
            'finance'        => 'Beranda, Kasir, Keuangan, Katalog, Stok, Laporan',
            'employee'       => 'Beranda, Katalog',
            'viewer'         => 'Beranda, Kasir, Keuangan, Katalog, Stok, Laporan, Karyawan',
        ];

        return $map[$role] ?? '—';
    }
}
