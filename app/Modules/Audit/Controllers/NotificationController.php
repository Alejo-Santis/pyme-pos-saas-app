<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\SystemNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API JSON de notificaciones (consumida por el AppLayout vía fetch periódico).
 */
class NotificationController extends Controller
{
    /** Devuelve las últimas notificaciones no leídas del usuario actual. */
    public function index(Request $request): JsonResponse
    {
        $notifications = SystemNotification::forUser(auth()->id())
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'type', 'title', 'body', 'icon', 'color', 'data', 'read', 'created_at']);

        $unreadCount = $notifications->where('read', false)->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /** Marca una notificación como leída. */
    public function markRead(SystemNotification $notification): JsonResponse
    {
        $notification->markAsRead();
        return response()->json(['ok' => true]);
    }

    /** Marca todas las notificaciones del usuario como leídas. */
    public function markAllRead(): JsonResponse
    {
        SystemNotification::forUser(auth()->id())
            ->unread()
            ->update(['read' => true, 'read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
