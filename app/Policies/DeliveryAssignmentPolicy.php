<?php

namespace App\Policies;

use App\Models\DeliveryAssignment;
use App\Models\User;

class DeliveryAssignmentPolicy
{
    /** A courier may only act on tasks assigned to them. */
    public function act(User $user, DeliveryAssignment $a): bool
    {
        return $user->isCourier() && $user->is_active && $a->courier->user_id === $user->id;
    }

    public function manage(User $user, DeliveryAssignment $a): bool
    {
        return $user->isStaff();
    }
}
