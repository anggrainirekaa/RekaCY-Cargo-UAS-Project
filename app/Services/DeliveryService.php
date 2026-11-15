<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\DocumentType;
use App\Enums\ShipmentStatus;
use App\Events\ShipmentUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Courier;
use App\Models\DeliveryAssignment;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Live;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Courier task workflow. Each step moves the assignment AND the shipment (through TrackingService,
 * so history, notifications and audit stay consistent) inside one transaction.
 *
 *   assign → accept → pickup(PICKED_UP) → start(IN_TRANSIT) → arrive → complete(DELIVERED + POD)
 *                                    └──────────── fail (opens DELIVERY_FAILED exception) ───────────┘
 */
class DeliveryService
{
    public function __construct(
        private TrackingService $tracking,
        private ExceptionService $exceptions,
        private DocumentService $documents,
        private AuditLogger $audit,
        private AlertService $alerts,
    ) {
    }

    // ---------------------------------------------------------------- staff side

    public function assign(Shipment $shipment, Courier $courier, User $actor, ?string $notes = null): DeliveryAssignment
    {
        $a = DB::transaction(function () use ($shipment, $courier, $actor, $notes) {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($shipment->status, [ShipmentStatus::Booked, ShipmentStatus::PickedUp, ShipmentStatus::InTransit], true)) {
                throw new BusinessRuleException('A courier can only be assigned to a BOOKED shipment (or re-assigned while it is picked up / in transit).');
            }

            $courier->loadMissing('user');
            if (! $courier->is_active || ! $courier->user->is_active) {
                throw new BusinessRuleException('This courier account is inactive.');
            }

            // Re-assignment: the previous active task is cancelled and its GPS stops.
            foreach ($shipment->assignments()->active()->get() as $old) {
                if ($old->courier_id === $courier->id) {
                    throw new BusinessRuleException('This courier already holds an active task for this shipment.');
                }
                $this->close($old, AssignmentStatus::Cancelled, 'Re-assigned by '.$actor->name);
            }

            $a = new DeliveryAssignment(['notes' => $notes]);
            $a->shipment_id = $shipment->id;
            $a->courier_id = $courier->id;
            $a->assigned_by = $actor->id;
            $a->status = AssignmentStatus::Assigned;
            $a->assigned_at = now();
            $a->save();

            $shipment->trackingEvents()->create([
                'event_type' => 'COURIER_ASSIGNED', 'occurred_at' => now(), 'created_by' => $actor->id,
                'description' => "Courier {$courier->user->name} assigned.",
            ]);

            $this->audit->log('assignment.created', $a, null, ['shipment_id' => $shipment->id, 'courier_id' => $courier->id], $actor);
            $shipment->loadMissing('customer');
            $this->alerts->toUser($courier->user, $shipment, 'New delivery task', "{$shipment->shipment_number} has been assigned to you.", 'info');
            $this->alerts->shipment($shipment, 'Courier assigned', "{$shipment->shipment_number}: courier {$courier->user->name} assigned.", 'info');

            return $a;
        });

        $this->broadcast($a, 'assignment', 'Courier assigned');

        return $a;
    }

    public function cancel(DeliveryAssignment $a, User $actor): DeliveryAssignment
    {
        DB::transaction(function () use ($a, $actor) {
            $a = DeliveryAssignment::query()->whereKey($a->id)->lockForUpdate()->firstOrFail();
            $this->guard($a, AssignmentStatus::Cancelled);
            $this->close($a, AssignmentStatus::Cancelled, 'Cancelled by '.$actor->name);
            $this->audit->log('assignment.cancelled', $a, null, null, $actor);
        });

        $this->broadcast($a->fresh(), 'assignment', 'Assignment cancelled');

        return $a->fresh();
    }

    // ---------------------------------------------------------------- courier side

    public function accept(DeliveryAssignment $a, User $courierUser): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::Accepted, $courierUser, function (DeliveryAssignment $a) {
            $a->accepted_at = now();
            $a->courier->forceFill(['availability' => 'ON_DELIVERY'])->save();
        }, 'Task accepted');
    }

    public function pickup(DeliveryAssignment $a, User $courierUser): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::PickedUp, $courierUser, function (DeliveryAssignment $a) use ($courierUser) {
            $busy = DeliveryAssignment::query()->where('courier_id', $a->courier_id)->whereKeyNot($a->id)
                ->whereIn('status', ['PICKED_UP', 'IN_TRANSIT', 'ARRIVED'])->exists();
            if ($busy) {
                throw new BusinessRuleException('Finish your current delivery before picking up another one.');
            }
            $a->picked_up_at = now();
            $this->tracking->record($a->shipment, ['status' => ShipmentStatus::PickedUp->value, 'description' => 'Courier picked up the cargo.'], $courierUser);
        }, 'Cargo picked up');
    }

    public function start(DeliveryAssignment $a, User $courierUser): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::InTransit, $courierUser, function (DeliveryAssignment $a) use ($courierUser) {
            $a->started_at = now();
            $this->tracking->record($a->shipment, ['status' => ShipmentStatus::InTransit->value, 'description' => 'Courier is on the way to the destination.'], $courierUser);
        }, 'Delivery started');
    }

    public function arrive(DeliveryAssignment $a, User $courierUser): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::Arrived, $courierUser, function (DeliveryAssignment $a) use ($courierUser) {
            $a->arrived_at = now();
            $last = $a->locations()->reorder()->latest('recorded_at')->first();
            $this->tracking->record($a->shipment, array_filter([
                'description' => 'Courier arrived at the destination.',
                'latitude' => $last?->latitude, 'longitude' => $last?->longitude, 'location_name' => $a->shipment->destination->name,
            ], fn ($v) => $v !== null), $courierUser);
        }, 'Arrived at destination');
    }

    /** Completing requires a recipient name and a photo proof; the shipment becomes DELIVERED. */
    public function complete(DeliveryAssignment $a, User $courierUser, string $recipient, UploadedFile $photo, ?string $notes = null): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::Completed, $courierUser, function (DeliveryAssignment $a) use ($courierUser, $recipient, $photo, $notes) {
            $a->completed_at = now();
            $a->recipient_name = $recipient;
            $a->notes = $notes ?? $a->notes;

            $this->documents->store($a->shipment, $photo, [
                'type' => DocumentType::Pod->value, 'title' => 'Proof of delivery — '.$recipient,
                'recipient_name' => $recipient, 'received_at' => now(), 'notes' => $notes,
            ], $courierUser);

            $this->tracking->record($a->shipment, [
                'status' => ShipmentStatus::Delivered->value, 'description' => "Delivered to {$recipient}.",
            ], $courierUser);

            $this->release($a);
        }, 'Delivery completed');
    }

    public function fail(DeliveryAssignment $a, User $courierUser, string $reason): DeliveryAssignment
    {
        return $this->step($a, AssignmentStatus::Failed, $courierUser, function (DeliveryAssignment $a) use ($courierUser, $reason) {
            $a->failure_reason = $reason;
            $a->completed_at = now();
            $this->exceptions->open($a->shipment, ['type' => 'DELIVERY_FAILED', 'severity' => 'HIGH', 'description' => $reason], $courierUser);
            $this->release($a);
        }, 'Delivery failed');
    }

    // ---------------------------------------------------------------- internals

    /** Run one validated transition inside a transaction, then broadcast. */
    private function step(DeliveryAssignment $a, AssignmentStatus $to, User $actor, callable $effects, string $message): DeliveryAssignment
    {
        $result = DB::transaction(function () use ($a, $to, $actor, $effects) {
            $a = DeliveryAssignment::query()->with(['shipment.destination', 'courier.user'])->whereKey($a->id)->lockForUpdate()->firstOrFail();

            if ($a->courier->user_id !== $actor->id) {
                throw new BusinessRuleException('This task is not assigned to you.');
            }

            $this->guard($a, $to);
            $from = $a->status;
            $effects($a);
            $a->status = $to;
            $a->save();

            $this->audit->log('assignment.'.strtolower($to->value), $a, ['status' => $from->value], ['status' => $to->value], $actor);

            return $a;
        });

        $this->broadcast($result, 'assignment', $message);

        return $result;
    }

    private function guard(DeliveryAssignment $a, AssignmentStatus $to): void
    {
        if (! $a->status->canTransitionTo($to)) {
            $allowed = $a->status->allowedTransitions() ? implode(', ', array_map(fn ($s) => $s->value, $a->status->allowedTransitions())) : 'none (finished)';
            throw new BusinessRuleException("Invalid task status change {$a->status->value} → {$to->value}. Allowed: {$allowed}.");
        }
    }

    /** Terminal bookkeeping without notifications (used by cancel/re-assign). */
    private function close(DeliveryAssignment $a, AssignmentStatus $to, string $note): void
    {
        $a->status = $to;
        $a->notes = trim(($a->notes ? $a->notes.' | ' : '').$note);
        $a->save();
        $this->release($a);
    }

    /** The courier stops being tracked and becomes available when no other task is in progress. */
    private function release(DeliveryAssignment $a): void
    {
        $courier = Courier::query()->find($a->courier_id);
        $stillBusy = DeliveryAssignment::query()->where('courier_id', $a->courier_id)->whereKeyNot($a->id)->active()->exists();

        $courier->forceFill([
            'is_tracking' => false,
            'availability' => $stillBusy ? 'ON_DELIVERY' : 'AVAILABLE',
        ])->save();
    }

    private function broadcast(DeliveryAssignment $a, string $type, string $message): void
    {
        $a->loadMissing('shipment');
        Live::dispatch(new ShipmentUpdated($a->shipment->fresh(), $type, $message));
    }
}
