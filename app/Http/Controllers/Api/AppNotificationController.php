<?php

namespace App\Http\Controllers\Api;

use App\AppNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->canAccessCompanyPanel() && $user->effectiveTenantId() !== null, 403);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);

        $notifications = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (AppNotification $notification): array => $this->formatNotification($notification)),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->canAccessCompanyPanel() && $user->effectiveTenantId() !== null, 403);

        $count = AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $notification->user_id === $user->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'notification' => $this->formatNotification($notification->fresh()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->canAccessCompanyPanel() && $user->effectiveTenantId() !== null, 403);

        AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(AppNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
