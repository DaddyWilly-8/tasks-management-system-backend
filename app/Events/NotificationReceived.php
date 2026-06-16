<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $notification;
    public int $unread_count;
    public int $user_id;

    public function __construct(Notification $notification, int $unreadCount)
    {
        $this->user_id = (int) $notification->user_id;
        $this->notification = [
            'id' => $notification->id,
            'title' => $notification->title,
            'message' => $notification->message,
            'type' => $notification->type,
            'is_read' => (bool) $notification->is_read,
            'created_at' => optional($notification->created_at)->toIso8601String(),
            'action_url' => $notification->action_url,
            'data' => $notification->data,
        ];
        $this->unread_count = $unreadCount;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.' . $this->user_id);
    }

    public function broadcastAs(): string
    {
        return 'NotificationReceived';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'unread_count' => $this->unread_count,
        ];
    }
}
