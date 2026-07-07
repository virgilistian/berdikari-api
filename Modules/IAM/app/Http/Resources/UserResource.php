<?php

namespace Modules\IAM\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'role'        => $this->role,        // legacy string column — kept for backward compat
            'business_id' => $this->business_id,
            'roles'       => $this->getRoleNames()->values()->all(),        // spatie: ['business-owner']
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(), // spatie: ['finance.view', ...]
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
