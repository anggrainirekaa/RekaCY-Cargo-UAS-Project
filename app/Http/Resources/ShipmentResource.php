<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Shipment */
class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $located = $this->relationLoaded('latestLocatedEvent') ? $this->latestLocatedEvent : null;
        $loc = fn ($l) => $l ? ['id' => $l->id, 'name' => $l->name, 'city' => $l->city, 'country' => $l->country, 'latitude' => $l->latitude, 'longitude' => $l->longitude] : null;

        return [
            'id' => $this->id,
            'shipment_number' => $this->shipment_number,
            'status' => $this->status->value,
            'priority' => $this->priority?->value ?? 'NORMAL',
            'customer' => $this->whenLoaded('customer', fn () => ['id' => $this->customer->id, 'name' => $this->customer->name]),
            'carrier' => $this->whenLoaded('carrier', fn () => ['id' => $this->carrier->id, 'name' => $this->carrier->name, 'code' => $this->carrier->code]),
            'origin' => $this->whenLoaded('origin', fn () => $loc($this->origin)),
            'destination' => $this->whenLoaded('destination', fn () => $loc($this->destination)),
            'cargo_type' => $this->cargo_type,
            'weight_kg' => $this->weight_kg,
            'volume_cbm' => $this->volume_cbm,
            'expected_departure_at' => $this->expected_departure_at?->toIso8601String(),
            'expected_arrival_at' => $this->expected_arrival_at?->toIso8601String(),
            'estimated_arrival_at' => $this->estimated_arrival_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delay' => [
                'is_delayed' => $this->is_delayed,
                'hours' => $this->delay_hours,
                'severity' => $this->delay_severity?->value,
            ],
            'current_location' => $located ? [
                'latitude' => $located->latitude,
                'longitude' => $located->longitude,
                'name' => $located->location_name,
                'as_of' => $located->occurred_at->toIso8601String(),
            ] : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'description' => $i->description, 'quantity' => $i->quantity, 'weight_kg' => $i->weight_kg,
            ])),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
