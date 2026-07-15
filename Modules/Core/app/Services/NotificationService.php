<?php

namespace Modules\Core\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\AppNotification;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     *
     * @param  array{type: string, title: string, body: string, meta?: array|null}  $data
     */
    public function notifyUser(string $businessId, string $userId, array $data): AppNotification
    {
        return AppNotification::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'type'        => $data['type'],
            'title'       => $data['title'],
            'body'        => $data['body'],
            'meta'        => $data['meta'] ?? null,
        ]);
    }

    /**
     * Broadcast a notification to all users in a business who hold a given role.
     * Uses role_target — resolved at read time in the frontend.
     *
     * @param  array{type: string, title: string, body: string, meta?: array|null}  $data
     */
    public function broadcastToRole(string $businessId, string $role, array $data): AppNotification
    {
        return AppNotification::create([
            'business_id' => $businessId,
            'user_id'     => null,
            'role_target' => $role,
            'type'        => $data['type'],
            'title'       => $data['title'],
            'body'        => $data['body'],
            'meta'        => $data['meta'] ?? null,
        ]);
    }

    /**
     * Get unread notifications for a user.
     * Includes both user-targeted and role-targeted notifications for their roles.
     *
     * @param  string[]  $userRoles
     */
    public function forUser(string $businessId, string $userId, array $userRoles = [], int $limit = 50)
    {
        return AppNotification::where('business_id', $businessId)
            ->where(function ($q) use ($userId, $userRoles) {
                $q->where('user_id', $userId);
                if (! empty($userRoles)) {
                    $q->orWhereIn('role_target', $userRoles);
                }
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Count unread notifications for a user.
     *
     * @param  string[]  $userRoles
     */
    public function unreadCount(string $businessId, string $userId, array $userRoles = []): int
    {
        return AppNotification::where('business_id', $businessId)
            ->whereNull('read_at')
            ->where(function ($q) use ($userId, $userRoles) {
                $q->where('user_id', $userId);
                if (! empty($userRoles)) {
                    $q->orWhereIn('role_target', $userRoles);
                }
            })
            ->count();
    }

    /**
     * Mark a specific notification as read.
     */
    public function markRead(AppNotification $notification): void
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    /**
     * Mark all notifications for a user as read.
     *
     * @param  string[]  $userRoles
     */
    public function markAllRead(string $businessId, string $userId, array $userRoles = []): int
    {
        return AppNotification::where('business_id', $businessId)
            ->whereNull('read_at')
            ->where(function ($q) use ($userId, $userRoles) {
                $q->where('user_id', $userId);
                if (! empty($userRoles)) {
                    $q->orWhereIn('role_target', $userRoles);
                }
            })
            ->update(['read_at' => now()]);
    }
}
