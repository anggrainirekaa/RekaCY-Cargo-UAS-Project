<?php

namespace App\Services;

use App\Events\CourierLocationUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Courier;
use App\Models\CourierLocation;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Support\Geo;
use App\Support\Live;
use Carbon\Carbon;

class CourierLocationService
{
    public const MAX_AGE_MINUTES = 10;   // older device timestamps are rejected as stale
    public const DEDUPE_METRES = 5;      // same spot ...
    public const DEDUPE_SECONDS = 10;    // ... reported again within this window is skipped

    /**
     * Validate and store one GPS point sent by a courier's own device.
     *
     * @param  array<string, mixed>  $d  assignment_id, latitude, longitude, accuracy?, speed_kmh?, heading?, recorded_at?, source?
     * @return array{stored: bool, location: ?CourierLocation, eta: ?array<string, mixed>}
     */
    public function record(Courier $courier, array $d): array
    {
        $assignment = DeliveryAssignment::query()->with('shipment.destination')
            ->where('courier_id', $courier->id)->whereKey($d['assignment_id'])->first();

        if (! $assignment) {
            throw new BusinessRuleException('This task is not assigned to you.');
        }

        if (! $assignment->status->tracksGps()) {
            throw new BusinessRuleException('GPS is only accepted while the delivery is in progress (picked up → arrived).');
        }

        if (! $courier->is_tracking) {
            throw new BusinessRuleException('Tracking is not started. Tap "Start tracking" first.');
        }

        $recordedAt = isset($d['recorded_at']) ? Carbon::parse($d['recorded_at']) : now();

        if ($recordedAt->greaterThan(now()->addMinutes(2))) {
            throw new BusinessRuleException('The location timestamp is in the future.');
        }
        if ($recordedAt->lessThan(now()->subMinutes(self::MAX_AGE_MINUTES))) {
            throw new BusinessRuleException('The location is too old to be useful and was rejected.');
        }

        $last = $assignment->locations()->reorder()->latest('recorded_at')->first();
        if ($last && abs($recordedAt->getTimestamp() - $last->recorded_at->getTimestamp()) < self::DEDUPE_SECONDS
            && Geo::km($last->latitude, $last->longitude, (float) $d['latitude'], (float) $d['longitude']) * 1000 < self::DEDUPE_METRES) {
            $courier->forceFill(['last_seen_at' => now()])->save();

            return ['stored' => false, 'location' => $last, 'eta' => null];
        }

        $loc = new CourierLocation([
            'latitude' => $d['latitude'], 'longitude' => $d['longitude'],
            'accuracy' => $d['accuracy'] ?? null, 'speed_kmh' => $d['speed_kmh'] ?? null, 'heading' => $d['heading'] ?? null,
            'source' => $d['source'] ?? 'device', 'recorded_at' => $recordedAt,
        ]);
        $loc->courier_id = $courier->id;
        $loc->delivery_assignment_id = $assignment->id;
        $loc->shipment_id = $assignment->shipment_id;
        $loc->save();

        $courier->forceFill(['last_seen_at' => now()])->save();

        $eta = $this->eta($assignment->shipment, $loc);
        Live::dispatch(new CourierLocationUpdated($assignment->shipment, $courier->loadMissing('user'), $loc, $eta));

        return ['stored' => true, 'location' => $loc, 'eta' => $eta];
    }

    /**
     * Live state for one shipment, as customers and staff are allowed to see it:
     * only while a delivery is in progress. Nothing is exposed after completion.
     *
     * @return array<string, mixed>
     */
    public function liveState(Shipment $shipment): array
    {
        $a = $shipment->assignments()->with('courier.user')->get()->first(fn ($x) => $x->status->tracksGps());

        if (! $a) {
            return ['state' => 'NONE', 'message' => 'No delivery in progress.', 'location' => null, 'eta' => null, 'courier' => null, 'assignment' => null];
        }

        $loc = $a->locations()->reorder()->latest('recorded_at')->first();
        $courier = $a->courier;
        $base = ['courier' => $courier->user->name, 'assignment' => ['id' => $a->id, 'status' => $a->status->value]];

        if (! $loc) {
            return $base + ['state' => 'NONE', 'message' => $courier->is_tracking ? 'Waiting for the first GPS position…' : 'Courier has not started GPS tracking yet.', 'location' => null, 'eta' => null];
        }

        $age = (int) now()->diffInSeconds($loc->recorded_at, true);
        $live = $courier->is_tracking && $age <= Courier::LIVE_SECONDS;

        return $base + [
            'state' => $live ? 'ACTIVE' : 'STALE',
            'message' => $live ? 'Live GPS' : ($courier->is_tracking ? 'Signal lost — showing last known position.' : 'Tracking paused — showing last known position.'),
            'location' => $this->present($loc),
            'age_seconds' => $age,
            'eta' => $this->eta($shipment->loadMissing('destination'), $loc),
        ];
    }

    /** @return array<string, mixed> */
    public function present(CourierLocation $l): array
    {
        return [
            'latitude' => $l->latitude, 'longitude' => $l->longitude, 'accuracy' => $l->accuracy,
            'speed_kmh' => $l->speed_kmh, 'heading' => $l->heading, 'source' => $l->source,
            'recorded_at' => $l->recorded_at->toIso8601String(),
        ];
    }

    /**
     * Realistic last-mile ETA: straight-line distance × 1.3 detour factor ÷ speed.
     * Speed is measured from the last 10 minutes of GPS points when possible, otherwise a 30 km/h default;
     * always clamped to 15–60 km/h. This is an estimate and is labelled as one in the UI.
     *
     * @return array<string, mixed>|null
     */
    public function eta(Shipment $shipment, CourierLocation $loc): ?array
    {
        $dest = $shipment->destination ?? $shipment->load('destination')->destination;
        if (! $dest) {
            return null;
        }

        $km = Geo::km($loc->latitude, $loc->longitude, $dest->latitude, $dest->longitude);
        $pts = CourierLocation::query()->where('delivery_assignment_id', $loc->delivery_assignment_id)
            ->where('recorded_at', '>=', $loc->recorded_at->copy()->subMinutes(10))->orderBy('recorded_at')->get(['latitude', 'longitude', 'recorded_at']);

        $speed = null;
        if ($pts->count() >= 2) {
            $dist = 0.0;
            for ($i = 1; $i < $pts->count(); $i++) {
                $dist += Geo::km($pts[$i - 1]->latitude, $pts[$i - 1]->longitude, $pts[$i]->latitude, $pts[$i]->longitude);
            }
            $hours = max(1, $pts->last()->recorded_at->getTimestamp() - $pts->first()->recorded_at->getTimestamp()) / 3600;
            $speed = $dist / $hours;
        }

        $measured = $speed !== null && $speed >= 3;
        $assumed = max(15.0, min(60.0, $measured ? $speed : 30.0));
        $road = $km * 1.3;
        $minutes = (int) ceil($road / $assumed * 60);

        return [
            'distance_km' => round($km, 1),
            'road_km_estimate' => round($road, 1),
            'speed_kmh' => round($assumed, 0),
            'basis' => $measured ? 'measured speed' : 'default 30 km/h',
            'minutes' => $minutes,
            'eta_at' => now()->addMinutes($minutes)->toIso8601String(),
        ];
    }
}
