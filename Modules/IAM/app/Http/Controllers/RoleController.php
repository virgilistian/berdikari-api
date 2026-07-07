<?php

namespace Modules\IAM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\IAM\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * @tags IAM — Manajemen Role
 */
class RoleController extends Controller
{
    /**
     * Daftar semua role yang tersedia dalam bisnis ini
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     { "id": 1, "name": "business-owner", "permissions": ["finance.view", "pos.view"] }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        // Only owners/managers may view role list
        if (! auth()->user()->hasAnyPermission(['role.assign', 'user.manage'])) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $businessId = auth()->user()->business_id;
        setPermissionsTeamId($businessId);

        $roles = Role::with('permissions')
            ->where('business_id', $businessId)
            ->get()
            ->map(fn(Role $r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'permissions' => $r->permissions->pluck('name')->values()->all(),
            ]);

        return response()->json(['success' => true, 'data' => $roles]);
    }

    /**
     * Perbarui daftar permission sebuah role
     *
     * Hanya dapat dilakukan oleh pengguna dengan permission **role.assign**.
     * Permission yang tidak ada dalam PermissionSeeder::PERMISSIONS akan diabaikan.
     *
     * @response 200 { "success": true, "data": { "id": 1, "name": "cashier", "permissions": ["pos.view"] }, "message": "Izin role berhasil diperbarui." }
     * @response 403 { "success": false, "message": "Akses ditolak." }
     * @response 404 { "success": false, "message": "Role tidak ditemukan." }
     */
    public function syncPermissions(Request $request, int $roleId): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('role.assign')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $businessId = auth()->user()->business_id;
        setPermissionsTeamId($businessId);

        $role = Role::where('id', $roleId)
            ->where('business_id', $businessId)
            ->first();

        if (! $role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan.'], 404);
        }

        // Prevent super-admin from being modified via this endpoint
        if ($role->name === 'super-admin') {
            return response()->json(['success' => false, 'message' => 'Role super-admin tidak dapat diubah melalui UI.'], 403);
        }

        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', PermissionSeeder::PERMISSIONS)],
        ], [
            'permissions.required' => 'Daftar izin wajib diisi.',
            'permissions.*.in'     => 'Salah satu izin tidak valid.',
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->fresh()->permissions->pluck('name')->values()->all(),
            ],
            'message' => 'Izin role berhasil diperbarui.',
        ]);
    }

    /**
     * Tambahkan role ke pengguna
     *
     * @response 200 {"success": true, "message": "Role berhasil ditetapkan."}
     * @response 403 {"success": false, "message": "Akses ditolak."}
     * @response 404 {"success": false, "message": "Role tidak ditemukan."}
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('role.assign')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $businessId = auth()->user()->business_id;

        // Ensure target user belongs to same business
        if ($user->business_id !== $businessId) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $request->validate(['role' => ['required', 'string']]);

        setPermissionsTeamId($businessId);

        $role = Role::where('name', $request->role)
            ->where('business_id', $businessId)
            ->first();

        if (! $role) {
            return response()->json(['success' => false, 'message' => 'Role tidak ditemukan.'], 404);
        }

        $user->assignRole($role);

        return response()->json(['success' => true, 'message' => 'Role berhasil ditetapkan.']);
    }

    /**
     * Hapus role dari pengguna
     *
     * @response 200 {"success": true, "message": "Role berhasil dihapus."}
     * @response 403 {"success": false, "message": "Akses ditolak."}
     */
    public function removeRole(Request $request, User $user, string $roleName): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('role.assign')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $businessId = auth()->user()->business_id;

        if ($user->business_id !== $businessId) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        setPermissionsTeamId($businessId);

        $user->removeRole($roleName);

        return response()->json(['success' => true, 'message' => 'Role berhasil dihapus.']);
    }
}
