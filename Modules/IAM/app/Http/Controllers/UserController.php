<?php

namespace Modules\IAM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\IAM\Http\Requests\StoreUserRequest;
use Modules\IAM\Http\Requests\UpdateUserRequest;
use Modules\IAM\Http\Resources\UserResource;

/**
 * @tags IAM — Manajemen Pengguna
 */
class UserController extends Controller
{
    /**
     * Daftar pengguna
     *
     * Mengembalikan semua pengguna dalam bisnis yang sama dengan pengguna login.
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "name": "Budi",
     *       "email": "budi@example.com",
     *       "role": "owner",
     *       "business_id": "uuid",
     *       "roles": ["business-owner"],
     *       "permissions": ["finance.view"],
     *       "created_at": "2024-01-01T00:00:00+00:00"
     *     }
     *   ]
     * }
     */
    public function index(): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('user.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik yang dapat mengelola pengguna.',
            ], 403);
        }

        $users = User::where('business_id', auth()->user()->business_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users),
        ]);
    }

    /**
     * Buat pengguna baru
     *
     * Hanya dapat dilakukan oleh pengguna dengan permission **user.manage**.
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "id": "uuid",
     *     "name": "Sari",
     *     "email": "sari@example.com",
     *     "role": "cashier",
     *     "business_id": "uuid",
     *     "roles": ["cashier"],
     *     "permissions": ["pos.view", "pos.open", "pos.close"],
     *     "created_at": "2024-01-01T00:00:00+00:00"
     *   },
     *   "message": "Pengguna berhasil dibuat."
     * }
     * @response 403 {
     *   "success": false,
     *   "message": "Hanya pemilik yang dapat mengelola pengguna."
     * }
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('user.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik yang dapat mengelola pengguna.',
            ], 403);
        }

        $validated = $request->validated();

        $user = User::create([
            'business_id' => auth()->user()->business_id,
            ...$validated,
        ]);

        // Assign the chosen role via spatie RBAC (if provided and exists)
        $businessId = auth()->user()->business_id;
        setPermissionsTeamId($businessId);

        $roleName = $validated['role'] ?? null;
        if ($roleName) {
            $user->assignRole($roleName);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => 'Pengguna berhasil dibuat.',
        ], 201);
    }

    /**
     * Detail pengguna
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": "uuid",
     *     "name": "Budi",
     *     "email": "budi@example.com",
     *     "role": "owner",
     *     "business_id": "uuid",
     *     "roles": ["business-owner"],
     *     "permissions": ["finance.view"],
     *     "created_at": "2024-01-01T00:00:00+00:00"
     *   }
     * }
     * @response 404 {
     *   "success": false,
     *   "message": "Data tidak ditemukan."
     * }
     */
    public function show(User $user): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('user.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik yang dapat mengelola pengguna.',
            ], 403);
        }

        if ($user->business_id !== auth()->user()->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Perbarui pengguna
     *
     * Hanya dapat dilakukan oleh pengguna dengan permission **user.manage**.
     * Semua field bersifat opsional (partial update).
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": "uuid",
     *     "name": "Sari Updated",
     *     "email": "sari@example.com",
     *     "role": "cashier",
     *     "business_id": "uuid",
     *     "roles": ["cashier"],
     *     "permissions": ["pos.view"],
     *     "created_at": "2024-01-01T00:00:00+00:00"
     *   },
     *   "message": "Pengguna berhasil diperbarui."
     * }
     * @response 403 {"success": false, "message": "Hanya pemilik yang dapat mengelola pengguna."}
     * @response 404 {"success": false, "message": "Data tidak ditemukan."}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('user.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik yang dapat mengelola pengguna.',
            ], 403);
        }

        if ($user->business_id !== auth()->user()->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
            'message' => 'Pengguna berhasil diperbarui.',
        ]);
    }

    /**
     * Hapus pengguna
     *
     * Hanya dapat dilakukan oleh pengguna dengan permission **user.manage**.
     * Tidak dapat menghapus akun sendiri.
     *
     * @response 200 {"success": true, "message": "Pengguna berhasil dihapus."}
     * @response 403 {"success": false, "message": "Hanya pemilik yang dapat mengelola pengguna."}
     * @response 404 {"success": false, "message": "Data tidak ditemukan."}
     * @response 422 {"success": false, "message": "Tidak dapat menghapus akun sendiri."}
     */
    public function destroy(User $user): JsonResponse
    {
        if (! auth()->user()->hasPermissionTo('user.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pemilik yang dapat mengelola pengguna.',
            ], 403);
        }

        if ($user->business_id !== auth()->user()->business_id) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan.',
            ], 404);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus akun sendiri.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengguna berhasil dihapus.',
        ]);
    }
}
