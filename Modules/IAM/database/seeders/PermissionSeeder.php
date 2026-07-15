<?php

namespace Modules\IAM\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Seeds all explicit resource.action permissions used in Berdikari.
 * Wildcard permissions are BANNED — every permission is explicit.
 * Guard: 'web' (Sanctum SPA) — spatie default.
 */
class PermissionSeeder extends Seeder
{
    /** @var string[] */
    public const PERMISSIONS = [
        // POS / Kasir
        'pos.view',
        'pos.open',
        'pos.close',

        // Finance / Keuangan
        'finance.view',
        'finance.create',
        'finance.update',
        'finance.delete',
        'finance.export',

        // Inventory / Stok
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.approve',

        // Catalog / Produk
        'catalog.view',
        'catalog.create',
        'catalog.update',
        'catalog.delete',

        // Reports / Laporan
        'report.view',
        'report.export',

        // Employee / Karyawan
        'employee.view',
        'employee.create',
        'employee.update',

        // Attendance / Absensi
        'attendance.view',
        'attendance.create',

        // Leave / Cuti & Izin
        'leave.view',
        'leave.create',
        'leave.approve',

        // Notifications / Notifikasi
        'notification.view',

        // Roles & Users
        'role.assign',
        'user.manage',

        // Business settings
        'business.manage',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('  ✓ ' . count(self::PERMISSIONS) . ' permissions seeded.');
    }
}
