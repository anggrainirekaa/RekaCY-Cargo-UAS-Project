<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use App\Events\UserNotified;
use App\Notifications\ShipmentAlert;
use App\Support\Live;
use Illuminate\Support\Facades\Notification;

class AlertService
{
    /** Notify the shipment's customer and (optionally) every active staff member. */
    public function shipment(Shipment $shipment, string $title, string $message, string $level = 'info', bool $includeStaff = false): void
    {
        $recipients = collect([$shipment->customer]);

        if ($includeStaff) {
            $recipients = $recipients->merge(
                User::query()->whereIn('role', ['admin', 'operator'])->where('is_active', true)->get()
            );
        }

        $recipients = $recipients->filter()->unique('id');

        Notification::send(
            $recipients,
            new ShipmentAlert($title, $message, $shipment->id, $shipment->shipment_number, $level)
        );

        foreach ($recipients as $r) {
            Live::dispatch(new UserNotified($r->id, $title, $message, $level, $shipment->id));
        }
    }

    /** Notify one specific user (e.g. the assigned courier). */
    public function toUser(\App\Models\User $user, Shipment $shipment, string $title, string $message, string $level = 'info'): void
    {
        $user->notify(new ShipmentAlert($title, $message, $shipment->id, $shipment->shipment_number, $level));
        Live::dispatch(new UserNotified($user->id, $title, $message, $level, $shipment->id));
    }
}
