<?php

namespace Tests\Feature\IAM\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\IAM\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared RBAC scaffolding for IAM feature tests.
 *
 * spatie/laravel-permission runs with teams enabled (team_foreign_key =
 * business_id). Every test operates within a single demo business so the
 * SetPermissionsTeamId middleware resolves the same team the seeded
 * permissions/roles belong to.
 */
trait InteractsWithRbac
{
    protected string $businessId = '019f2e4c-0000-7000-8000-000000000001';

    /** Create every canonical permission (guard: web) for the demo business team. */
    protected function seedPermissions(): void
    {
        // RefreshDatabase re-migrates between tests, so any permissions cached
        // by spatie in a previous test now reference stale IDs. Clear the cache
        // before re-seeding to avoid foreign-key failures.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // The demo business must exist because users.business_id is a FK.
        DB::table('businesses')->insertOrIgnore([
            'id'         => $this->businessId,
            'name'       => 'Test Business',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        setPermissionsTeamId($this->businessId);

        foreach (PermissionSeeder::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    /**
     * Create a user in the demo business and grant it the given permissions
     * directly (guard: web).
     *
     * @param  string[]  $permissions
     */
    protected function makeUser(array $permissions = [], string $legacyRole = 'staff', ?string $email = null): User
    {
        $user = User::create([
            'business_id' => $this->businessId,
            'name'        => 'User '.substr(uniqid(), -6),
            'email'       => $email ?? ('u'.uniqid().'@test.com'),
            'password'    => bcrypt('password'),
            'role'        => $legacyRole,
        ]);

        setPermissionsTeamId($this->businessId);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    /** Create a business-scoped role with the given permissions. */
    protected function makeRole(string $name, array $permissions = []): Role
    {
        setPermissionsTeamId($this->businessId);

        $role = Role::firstOrCreate([
            'name'        => $name,
            'guard_name'  => 'web',
            'business_id' => $this->businessId,
        ]);

        if ($permissions !== []) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Act with a different bearer token within the same test. The sanctum
     * guard caches the first resolved user for the whole test case, so the
     * guards must be flushed before switching identities.
     */
    protected function actingWithToken(string $token): static
    {
        app('auth')->forgetGuards();

        return $this->withToken($token);
    }
}
