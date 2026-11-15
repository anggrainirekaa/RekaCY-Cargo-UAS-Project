<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Events\ShipmentUpdated;
use App\Support\Live;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrackingService
{
    public function __construct(
        private ShipmentService $shipments,
        private AuditLogger $audit,
        private AlertService $alerts,
    ) {
    }

    /**
     * Append a tracking event. Never edits earlier events.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(Shipment $shipment, array $data, User $actor): TrackingEvent
    {
        $event = DB::transaction(function () use ($shipment, $data, $actor) {
            // Lock the row so two operators cannot race a status change.
            $shipment = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();

            if ($shipment->status->isTerminal()) {
                throw new BusinessRuleException("Shipment is {$shipment->status->value}; no further tracking events can be added.");
            }

            $occurredAt = isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now();
            $this->assertChronological($shipment, $occurredAt);

            $to = isset($data['status']) ? ShipmentStatus::from($data['status']) : null;
            $statusChanged = $to !== null && $to !== $shipment->status;

            if ($statusChanged) {
                $this->shipments->transition($shipment, $to, $actor, $occurredAt);
            }

            if (! empty($data['estimated_arrival_at'])) {
                $shipment->estimated_arrival_at = Carbon::parse($data['estimated_arrival_at']);
                $shipment->save();
            }

            [$locationId, $name, $lat, $lng] = $this->resolveLocation($data);

            $event = $shipment->trackingEvents()->create([
                'event_type' => $statusChanged ? $to->value : 'CHECKPOINT',
                'status' => $statusChanged ? $to : null,
                'location_id' => $locationId,
                'location_name' => $name,
                'latitude' => $lat,
                'longitude' => $lng,
                'occurred_at' => $occurredAt,
                'description' => $data['description'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->shipments->reevaluateDelay($shipment, null, $actor);
            $this->audit->log('tracking.recorded', $shipment, null, ['event_id' => $event->id, 'event_type' => $event->event_type], $actor);

            if ($statusChanged) {
                $shipment->loadMissing('customer');
                $this->alerts->shipment(
                    $shipment,
                    'Status: '.$to->label(),
                    "{$shipment->shipment_number} is now {$to->label()}.",
                    $to === ShipmentStatus::Delivered ? 'success' : 'info',
                );
            }

            return $event;
        });

        // Broadcast only after the transaction has committed, so clients never see uncommitted state.
        $fresh = Shipment::query()->find($event->shipment_id);
        Live::dispatch(new ShipmentUpdated($fresh, 'status', $event->status?->label() ?? ucwords(strtolower(str_replace('_', ' ', $event->event_type)))));

        return $event;
    }

    /** @return array{latitude: float, longitude: float, name: ?string, as_of: Carbon}|null */
    public function currentLocation(Shipment $shipment): ?array
    {
        $e = $shipment->latestLocatedEvent;

        return $e ? [
            'latitude' => $e->latitude,
            'longitude' => $e->longitude,
            'name' => $e->location_name,
            'as_of' => $e->occurred_at,
        ] : null;
    }

    private function assertChronological(Shipment $shipment, Carbon $at): void
    {
        $tolerance = (int) config('rekacy.tracking.future_tolerance_minutes', 5);

        if ($at->greaterThan(now()->addMinutes($tolerance))) {
            throw new BusinessRuleException('Tracking events cannot be dated in the future.');
        }

        $latest = $shipment->trackingEvents()->max('occurred_at');

        if ($latest && $at->lessThan(Carbon::parse($latest))) {
            throw new BusinessRuleException(
                'Event time must not be earlier than the latest recorded event ('.Carbon::parse($latest)->format('d M Y H:i').').'
            );
        }
    }

    /** @return array{0: ?int, 1: ?string, 2: ?float, 3: ?float} */
    private function resolveLocation(array $data): array
    {
        $location = ! empty($data['location_id']) ? Location::find($data['location_id']) : null;

        $lat = $data['latitude'] ?? $location?->latitude;
        $lng = $data['longitude'] ?? $location?->longitude;

        return [
            $location?->id,
            $data['location_name'] ?? $location?->name,
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ];
    }
}
