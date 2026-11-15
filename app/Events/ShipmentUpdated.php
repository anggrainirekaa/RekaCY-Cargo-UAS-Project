<?php

namespace App\Events;

use App\Models\Shipment;
use App\Services\LiveMetrics;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ShipmentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Shipment $shipment, public string $type = 'status', public ?string $message = null)
    {
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff.live'), new PrivateChannel('shipment.'.$this->shipment->id)];
    }

    public function broadcastAs(): string
    {
        return 'shipment.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $a = $this->shipment->assignments()->with('courier.user')->first();

        return [
            'type' => $this->type,
            'message' => $this->message,
            'shipment' => [
                'id' => $this->shipment->id,
                'number' => $this->shipment->shipment_number,
                'status' => $this->shipment->status->value,
                'status_label' => $this->shipment->status->label(),
            ],
            'assignment' => $a ? ['id' => $a->id, 'status' => $a->status->value, 'courier' => $a->courier->user->name] : null,
            'metrics' => app(LiveMetrics::class)->snapshot(),
            'at' => now()->toIso8601String(),
        ];
    }
}
