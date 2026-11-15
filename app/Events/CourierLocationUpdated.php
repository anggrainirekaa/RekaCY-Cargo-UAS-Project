<?php

namespace App\Events;

use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\Shipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Sent only to authorised private channels: staff, and the shipment's own customer. */
class CourierLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /** @param array<string, mixed>|null $eta */
    public function __construct(public Shipment $shipment, public Courier $courier, public CourierLocation $location, public ?array $eta = null)
    {
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('staff.live'), new PrivateChannel('shipment.'.$this->shipment->id)];
    }

    public function broadcastAs(): string
    {
        return 'courier.location';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'shipment_id' => $this->shipment->id,
            'shipment_number' => $this->shipment->shipment_number,
            'courier' => $this->courier->user->name,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'accuracy' => $this->location->accuracy,
            'speed_kmh' => $this->location->speed_kmh,
            'source' => $this->location->source,
            'recorded_at' => $this->location->recorded_at->toIso8601String(),
            'eta' => $this->eta,
        ];
    }
}
