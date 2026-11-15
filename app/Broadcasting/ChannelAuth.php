<?php

namespace App\Broadcasting;

use App\Models\Shipment;
use App\Models\User;

/** Authorisation rules for private WebSocket channels (kept in a class so they are unit-testable). */
final class ChannelAuth
{
    public static function staff(User $user): bool
    {
        return $user->is_active && $user->isStaff();
    }

    public static function shipment(User $user, int $shipmentId): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        $shipment = Shipment::query()->find($shipmentId);

        return $shipment !== null && $user->isCustomer() && $shipment->customer_id === $user->id;
    }

    public static function user(User $user, int $id): bool
    {
        return $user->id === $id;
    }
}
