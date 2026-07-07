<?php

namespace Modules\IAM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Modules\IAM\Http\Requests\LoginRequest;
use Modules\IAM\Http\Resources\UserResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Set team context so spatie resolves business-scoped roles & permissions
        // before UserResource calls getRoleNames() / getAllPermissions().
        setPermissionsTeamId($user->business_id);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'user'  => new UserResource($user),
            ],
            'message' => 'Login berhasil.',
        ]);
    }

    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new UserResource(auth()->user()),
        ]);
    }
}
