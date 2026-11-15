<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Real-time companion of the stored in-app notification (toast + unread badge). */
class UserNotified implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId, public string $title, public string $message, public string $level = 'info', public ?int $shipmentId = null)
    {
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.notified';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'level' => $this->level, 'shipment_id' => $this->shipmentId];
    }
}
