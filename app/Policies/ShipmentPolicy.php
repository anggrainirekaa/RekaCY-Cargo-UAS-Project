<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // lists are scoped with Shipment::visibleTo()
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() || $shipment->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    public function recordTracking(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function manageExceptions(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }
}
