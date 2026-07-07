<?php

namespace Modules\IAM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the spatie/laravel-permission team context to the authenticated user's business_id.
 * Must run AFTER auth:sanctum middleware so the user is already resolved.
 * Without this, all permission checks would be unscoped (no tenant isolation).
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if ($user) {
            setPermissionsTeamId($user->business_id);
        }

        return $next($request);
    }
}
