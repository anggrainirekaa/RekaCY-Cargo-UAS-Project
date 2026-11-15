<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Models\ShipmentException;

/** Counters pushed to the live admin dashboard. Every value is a database query. */
class LiveMetrics
{
    /** @return array<string, int> */
    public function snapshot(): array
    {
        $status = Shipment::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $n = fn (ShipmentStatus $s) => (int) ($status[$s->value] ?? 0);

        $failed = DeliveryAssignment::query()->where('status', 'FAILED')
            ->whereNotExists(fn ($q) => $q->from('delivery_assignments as a2')
                ->whereColumn('a2.shipment_id', 'delivery_assignments.shipment_id')
                ->whereColumn('a2.id', '>', 'delivery_assignments.id'))
            ->count();

        $open = [ExceptionStatus::Open->value, ExceptionStatus::Investigating->value];

        return [
            'total' => (int) $status->sum(),
            'waiting' => $n(ShipmentStatus::Created) + $n(ShipmentStatus::Booked),
            'processing' => $n(ShipmentStatus::PickedUp),
            'in_transit' => $n(ShipmentStatus::InTransit),
            'delivered' => $n(ShipmentStatus::Delivered),
            'failed' => $failed,
            'delayed' => Shipment::query()->active()->where('is_delayed', true)->count(),
            'exceptions' => ShipmentException::count(),
            'critical' => ShipmentException::whereIn('status', $open)->where('severity', 'HIGH')->pluck('shipment_id')
                ->merge(Shipment::query()->active()->where('delay_severity', 'HIGH')->pluck('id'))->unique()->count(),
            'couriers_tracking' => Courier::where('is_tracking', true)->count(),
            'couriers_live' => Courier::where('is_tracking', true)->where('last_seen_at', '>=', now()->subSeconds(Courier::LIVE_SECONDS))->count(),
            'couriers_available' => Courier::where('is_active', true)->where('availability', 'AVAILABLE')->count(),
        ];
    }
}
