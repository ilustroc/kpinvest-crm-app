<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class UserNotificationService
{
    public function list(User $user, int $limit = 15): array
    {
        $notifications = $user->notifications()
            ->latest()
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serialize($notification))
            ->all();

        return [
            'unread_count' => $this->unreadCount($user),
            'notifications' => $notifications,
        ];
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $id): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notification->markAsRead();

        return [
            'unread_count' => $this->unreadCount($user),
            'notification' => $this->serialize($notification->fresh()),
        ];
    }

    public function markAllAsRead(User $user): int
    {
        $user->unreadNotifications->markAsRead();

        return $this->unreadCount($user);
    }

    private function serialize(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'module' => $data['module'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'dni' => $data['dni'] ?? null,
            'cliente' => $data['cliente'] ?? null,
            'estado' => $data['estado'] ?? null,
            'title' => $data['title'] ?? 'Notificacion',
            'message' => $data['message'] ?? '',
            'action_label' => $data['action_label'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'created_by_name' => $data['created_by_name'] ?? null,
            'read_at' => optional($notification->read_at)->toISOString(),
            'created_at' => optional($notification->created_at)->toISOString(),
            'created_at_label' => optional($notification->created_at)->format('d/m/Y H:i'),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
