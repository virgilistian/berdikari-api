<?php

namespace Modules\IAM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\IAM\Http\Requests\ChangePasswordRequest;
use Modules\IAM\Http\Requests\UpdateProfileRequest;
use Modules\IAM\Http\Resources\UserResource;

/**
 * @tags IAM — Profil Pengguna
 */
class ProfileController extends Controller
{
    /**
     * Perbarui profil pengguna yang sedang login
     *
     * @response 200 {
     *   "success": true,
     *   "data": { "id": "uuid", "name": "Budi", "email": "budi@example.com" },
     *   "message": "Profil berhasil diperbarui."
     * }
     * @response 422 { "message": "Email sudah digunakan oleh akun lain." }
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->update($request->validated());

        // Re-set team context so UserResource resolves the correct scoped permissions
        setPermissionsTeamId($user->business_id);

        return response()->json([
            'success' => true,
            'data'    => new UserResource($user),
            'message' => 'Profil berhasil diperbarui.',
        ]);
    }

    /**
     * Ganti kata sandi pengguna yang sedang login
     *
     * @response 200 { "success": true, "message": "Kata sandi berhasil diubah." }
     * @response 422 { "success": false, "message": "Kata sandi saat ini salah." }
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi saat ini salah.',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diubah.',
        ]);
    }
}
