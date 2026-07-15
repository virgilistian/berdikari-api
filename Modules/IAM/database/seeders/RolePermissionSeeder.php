<?php

namespace Modules\IAM\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Creates the 10 standard Berdikari roles and assigns permissions to each.
 * Roles are business-scoped (team_id = business_id).
 * Permissions are global (not team-scoped) — only roles are team-scoped.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Role slug => array of permission names it holds.
     * Follows least-privilege: each role gets only what it needs.
     *
     * @var array<string, string[]>
     */
    public const ROLE_PERMISSIONS = [
        'super-admin' => [
            // super-admin bypasses all checks via spatie gate — list here for documentation only
            'pos.view', 'pos.open', 'pos.close',
            'finance.view', 'finance.create', 'finance.update', 'finance.delete', 'finance.export',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.approve',
            'catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete',
            'report.view', 'report.export',
            'employee.view', 'employee.create', 'employee.update',
            'attendance.view', 'attendance.create',
            'leave.view', 'leave.create', 'leave.approve',
            'notification.view',
            'role.assign', 'user.manage', 'business.manage',
        ],

        'business-owner' => [
            'pos.view', 'pos.open', 'pos.close',
            'finance.view', 'finance.create', 'finance.update', 'finance.delete', 'finance.export',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.approve',
            'catalog.view', 'catalog.create', 'catalog.update', 'catalog.delete',
            'report.view', 'report.export',
            'employee.view', 'employee.create', 'employee.update',
            'attendance.view', 'attendance.create',
            'leave.view', 'leave.create', 'leave.approve',
            'notification.view',
            'role.assign', 'user.manage', 'business.manage',
        ],

        'manager' => [
            'pos.view', 'pos.open', 'pos.close',
            'finance.view', 'finance.create', 'finance.update', 'finance.export',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.approve',
            'catalog.view', 'catalog.create', 'catalog.update',
            'report.view', 'report.export',
            'employee.view', 'employee.create', 'employee.update',
            'attendance.view', 'attendance.create',
            'leave.view', 'leave.create', 'leave.approve',
            'notification.view',
            'role.assign',
        ],

        'supervisor' => [
            'pos.view', 'pos.open', 'pos.close',
            'finance.view',
            'inventory.view', 'inventory.create', 'inventory.update', 'inventory.approve',
            'catalog.view',
            'report.view',
            'employee.view',
            'attendance.view', 'attendance.create',
            'leave.view', 'leave.create', 'leave.approve',
            'notification.view',
        ],

        'cashier' => [
            'pos.view', 'pos.open', 'pos.close',
            'catalog.view',
            'inventory.view',
            'attendance.create',
            'leave.create',
            'notification.view',
        ],

        'kitchen-staff' => [
            'inventory.view', 'inventory.create', 'inventory.update',
            'catalog.view',
            'attendance.create',
            'leave.create',
            'notification.view',
        ],

        'inventory-staff' => [
            'inventory.view', 'inventory.create', 'inventory.update',
            'catalog.view',
            'attendance.create',
            'leave.create',
            'notification.view',
        ],

        'finance' => [
            'finance.view', 'finance.create', 'finance.update', 'finance.delete', 'finance.export',
            'pos.view',
            'inventory.view',
            'catalog.view',
            'report.view', 'report.export',
            'notification.view',
        ],

        'employee' => [
            'catalog.view',
            'attendance.create',
            'leave.create',
            'notification.view',
        ],

        'viewer' => [
            'pos.view',
            'finance.view',
            'inventory.view',
            'catalog.view',
            'report.view',
            'employee.view',
            'attendance.view',
            'leave.view',
            'notification.view',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get the demo business (or null for super-admin which is system-wide)
        $businessId = DB::table('businesses')->value('id');

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            // super-admin is global (no team scope)
            $teamId = ($roleName === 'super-admin') ? null : $businessId;

            // Set team context for this role
            setPermissionsTeamId($teamId);

            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'business_id' => $teamId],
            );

            $role->syncPermissions($permissions);
        }

        $this->command->info('  ✓ ' . count(self::ROLE_PERMISSIONS) . ' roles seeded with permissions.');
    }
}
