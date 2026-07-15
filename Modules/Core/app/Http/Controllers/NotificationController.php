<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\AppNotification;
use Modules\Core\Services\NotificationService;

/**
 * @tags Core — Notifikasi In-App
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationService $service) {}

    private function businessId(): string
    {
        return (string) Auth::user()?->business_id;
    }

    private function userId(): string
    {
        return (string) Auth::id();
    }

    private function userRoles(): array
    {
        return Auth::user()?->roles?->pluck('name')->toArray() ?? [];
    }

    /**
     * Daftar notifikasi
     *
     * Mengembalikan notifikasi untuk pengguna yang login (personal + role-based).
     *
     * @queryParam limit integer Jumlah notifikasi (default: 50). Example: 20
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 50);
        $limit = min(max($limit, 1), 100);

        $notifications = $this->service->forUser(
            $this->businessId(),
            $this->userId(),
            $this->userRoles(),
            $limit
        );

        $unread = $this->service->unreadCount(
            $this->businessId(),
            $this->userId(),
            $this->userRoles()
        );

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unread,
        ]);
    }

    /**
     * Jumlah notifikasi belum dibaca
     *
     * Endpoint ringan untuk polling badge angka notifikasi.
     */
    public function unreadCount(): JsonResponse
    {
        $count = $this->service->unreadCount(
            $this->businessId(),
            $this->userId(),
            $this->userRoles()
        );

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Tandai notifikasi sebagai dibaca
     *
     * @urlParam id string required UUID notifikasi. Example: uuid
     *
     * @response 200 {"message":"Notifikasi ditandai sebagai dibaca."}
     */
    public function markRead(string $id): JsonResponse
    {
        $notification = AppNotification::where('business_id', $this->businessId())
            ->where(function ($q) {
                $q->where('user_id', $this->userId())
                    ->orWhereIn('role_target', $this->userRoles());
            })
            ->findOrFail($id);

        $this->service->markRead($notification);

        return response()->json(['message' => 'Notifikasi ditandai sebagai dibaca.']);
    }

    /**
     * Tandai semua notifikasi sebagai dibaca
     *
     * @response 200 {"message":"X notifikasi ditandai sebagai dibaca.","count":5}
     */
    public function markAllRead(): JsonResponse
    {
        $count = $this->service->markAllRead(
            $this->businessId(),
            $this->userId(),
            $this->userRoles()
        );

        return response()->json([
            'message' => "{$count} notifikasi ditandai sebagai dibaca.",
            'count'   => $count,
        ]);
    }
}
