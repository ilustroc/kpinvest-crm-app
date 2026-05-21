<?php

namespace App\Http\Controllers;

use App\Services\Notification\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly UserNotificationService $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->notifications->list($request->user(), (int) $request->integer('limit', 15))
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->notifications->markAsRead($request->user(), $id)
        );
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notifications->markAllAsRead($request->user()),
        ]);
    }
}
