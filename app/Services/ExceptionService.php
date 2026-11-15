<?php

namespace App\Services;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\Role;
use App\Enums\Severity;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ExceptionNote;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExceptionService
{
    public function __construct(private AuditLogger $audit, private AlertService $alerts)
    {
    }

    /** @param array<string, mixed> $data */
    public function open(Shipment $shipment, array $data, User $actor): ShipmentException
    {
        if ($shipment->status === ShipmentStatus::Cancelled) {
            throw new BusinessRuleException('Exceptions cannot be opened for a cancelled shipment.');
        }

        return DB::transaction(function () use ($shipment, $data, $actor) {
            $type = ExceptionType::from($data['type']);
            $severity = isset($data['severity']) ? Severity::from($data['severity']) : $type->defaultSeverity();
            $assignee = $this->resolveAssignee($data['assigned_to'] ?? null);

            $exception = $shipment->exceptions()->create([
                'type' => $type,
                'severity' => $severity,
                'status' => ExceptionStatus::Open,
                'description' => $data['description'],
                'detected_at' => now(),
                'created_by' => $actor->id,
                'assigned_to' => $assignee?->id,
            ]);

            $shipment->trackingEvents()->create([
                'event_type' => 'EXCEPTION',
                'occurred_at' => now(),
                'description' => "{$type->label()} ({$severity->value}): {$data['description']}",
                'is_exception' => true,
                'created_by' => $actor->id,
            ]);

            $this->audit->log('exception.opened', $exception, null, ['type' => $type->value, 'severity' => $severity->value, 'assigned_to' => $assignee?->id], $actor);

            $shipment->loadMissing('customer');
            $this->alerts->shipment(
                $shipment,
                'Exception: '.$type->label(),
                "{$shipment->shipment_number}: {$data['description']}",
                $severity === Severity::High ? 'danger' : 'warning',
                includeStaff: true,
            );

            return $exception;
        });
    }

    /** @param array<string, mixed> $data  status, severity, assigned_to, resolution_note */
    public function update(ShipmentException $exception, array $data, User $actor): ShipmentException
    {
        return DB::transaction(function () use ($exception, $data, $actor) {
            $old = ['status' => $exception->status->value, 'severity' => $exception->severity->value, 'assigned_to' => $exception->assigned_to];

            if (isset($data['severity'])) {
                $exception->severity = Severity::from($data['severity']);
            }

            if (array_key_exists('assigned_to', $data)) {
                $exception->assigned_to = $this->resolveAssignee($data['assigned_to'])?->id;
            }

            if (isset($data['status'])) {
                $to = ExceptionStatus::from($data['status']);

                if ($to !== $exception->status) {
                    if (! $exception->status->canTransitionTo($to)) {
                        throw new BusinessRuleException("Invalid exception status change {$exception->status->value} → {$to->value}.");
                    }

                    $exception->status = $to;

                    if ($to === ExceptionStatus::Resolved) {
                        $exception->resolved_at = now();
                        $exception->resolved_by = $actor->id;
                        $exception->resolution_note = $data['resolution_note'] ?? null;

                        $exception->shipment->trackingEvents()->create([
                            'event_type' => 'EXCEPTION_RESOLVED',
                            'occurred_at' => now(),
                            'description' => "{$exception->type->label()} resolved".($exception->resolution_note ? ": {$exception->resolution_note}" : '.'),
                            'created_by' => $actor->id,
                        ]);
                    }
                }
            }

            $exception->save();
            $this->audit->log('exception.updated', $exception, $old, ['status' => $exception->status->value, 'severity' => $exception->severity->value, 'assigned_to' => $exception->assigned_to], $actor);

            return $exception;
        });
    }

    /** Investigation notes are internal (staff only) and append-only. */
    public function addNote(ShipmentException $exception, string $body, User $actor): ExceptionNote
    {
        return DB::transaction(function () use ($exception, $body, $actor) {
            $note = $exception->notes()->create(['user_id' => $actor->id, 'body' => $body]);
            $this->audit->log('exception.note_added', $exception, null, ['note_id' => $note->id], $actor);

            return $note;
        });
    }

    private function resolveAssignee(mixed $id): ?User
    {
        if (empty($id)) {
            return null;
        }

        $user = User::query()->whereKey($id)->where('is_active', true)->whereIn('role', [Role::Admin->value, Role::Operator->value])->first();

        if (! $user) {
            throw new BusinessRuleException('The responsible person must be an active operator or administrator.');
        }

        return $user;
    }
}
