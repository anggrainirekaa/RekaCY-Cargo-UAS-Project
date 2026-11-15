<?php

namespace App\Services;

use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public function __construct(
        private AuditLogger $audit,
        private EtaService $eta,
        private AlertService $alerts,
    ) {
    }

    /** @param array<string, mixed> $data validated payload incl. items[] */
    public function create(array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($data, $actor) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            $shipment = new Shipment($data);
            $shipment->shipment_number = $this->nextNumber();
            $shipment->status = ShipmentStatus::Created;
            $shipment->created_by = $actor->id;
            $this->eta->apply($shipment, $this->eta->evaluate($shipment));
            $shipment->save();

            foreach ($items as $item) {
                $shipment->items()->create($item);
            }

            $shipment->load(['origin', 'customer']);
            $shipment->trackingEvents()->create([
                'event_type' => 'CREATED',
                'status' => ShipmentStatus::Created,
                'location_id' => $shipment->origin_id,
                'location_name' => $shipment->origin->name,
                'latitude' => $shipment->origin->latitude,
                'longitude' => $shipment->origin->longitude,
                'occurred_at' => now(),
                'description' => 'Shipment record created.',
                'created_by' => $actor->id,
            ]);

            $this->audit->log('shipment.created', $shipment, null, $shipment->only(['shipment_number', 'customer_id', 'carrier_id', 'origin_id', 'destination_id']), $actor);
            $this->alerts->shipment($shipment, 'Shipment created', "{$shipment->shipment_number} has been registered.", 'info');

            return $shipment;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Shipment $shipment, array $data, User $actor): Shipment
    {
        if ($shipment->status->isTerminal()) {
            throw new BusinessRuleException('Delivered or cancelled shipments can no longer be edited.');
        }

        return DB::transaction(function () use ($shipment, $data, $actor) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $shipment->fill($data);
            $dirty = $shipment->getDirty();
            $old = collect($dirty)->mapWithKeys(fn ($v, $k) => [$k => $shipment->getOriginal($k)])->all();
            $shipment->save();

            if (is_array($items)) {
                $shipment->items()->delete();
                foreach ($items as $item) {
                    $shipment->items()->create($item);
                }
            }

            $this->reevaluateDelay($shipment, null, $actor);
            $this->audit->log('shipment.updated', $shipment, $old, $dirty, $actor);

            return $shipment;
        });
    }

    /** Deletion is limited to shipments that never left the shipper. */
    public function delete(Shipment $shipment, User $actor): void
    {
        if (! in_array($shipment->status, [ShipmentStatus::Created, ShipmentStatus::Cancelled], true)) {
            throw new BusinessRuleException('Only CREATED or CANCELLED shipments can be deleted. Cancel the shipment first.');
        }

        DB::transaction(function () use ($shipment, $actor) {
            $this->audit->log('shipment.deleted', $shipment, $shipment->only(['shipment_number', 'status']), null, $actor);
            $shipment->delete();
        });
    }

    /**
     * Enforces the lifecycle rules. Called only from TrackingService (inside its transaction)
     * so that a status change always produces a tracking event.
     */
    public function transition(Shipment $shipment, ShipmentStatus $to, User $actor, ?CarbonInterface $at = null): Shipment
    {
        $from = $shipment->status;

        if (! $from->canTransitionTo($to)) {
            $allowed = $from->allowedTransitions()
                ? implode(', ', array_map(fn ($s) => $s->value, $from->allowedTransitions()))
                : 'none (terminal status)';

            throw new BusinessRuleException("Invalid status transition {$from->value} → {$to->value}. Allowed from {$from->value}: {$allowed}.");
        }

        $shipment->status = $to;
        if ($to === ShipmentStatus::Delivered) {
            $shipment->delivered_at = $at ?? now();
        }
        $shipment->save();

        $this->audit->log('shipment.status_changed', $shipment, ['status' => $from->value], ['status' => $to->value], $actor);

        return $shipment;
    }

    /**
     * Recalculate delay snapshot; records a DELAY_DETECTED event + alerts when a delay
     * appears or its severity increases.
     */
    public function reevaluateDelay(Shipment $shipment, ?CarbonInterface $now = null, ?User $actor = null): DelayResult
    {
        $wasDelayed = (bool) $shipment->is_delayed;
        $before = $shipment->delay_severity;

        $result = $this->eta->evaluate($shipment, $now);
        $this->eta->apply($shipment, $result);

        if ($shipment->isDirty(['is_delayed', 'delay_hours', 'delay_severity'])) {
            $shipment->save();
        }

        $worsened = $result->isDelayed && (! $wasDelayed || $before !== $result->severity);

        if ($worsened && ! $shipment->status->isTerminal()) {
            $shipment->trackingEvents()->create([
                'event_type' => 'DELAY_DETECTED',
                'occurred_at' => $now ?? now(),
                'description' => $result->reason,
                'is_exception' => true,
                'created_by' => $actor?->id,
            ]);

            $shipment->loadMissing('customer');
            $this->alerts->shipment(
                $shipment,
                $result->escalate ? 'Delay escalated' : 'Shipment delayed',
                "{$shipment->shipment_number}: {$result->reason}",
                $result->severity === Severity::High || $result->escalate ? 'danger' : 'warning',
                includeStaff: true,
            );
        }

        return $result;
    }

    private function nextNumber(): string
    {
        $next = (int) Shipment::withTrashed()->max('id') + 1;

        return sprintf('RCY-%s-%06d', now()->format('Y'), $next);
    }
}
