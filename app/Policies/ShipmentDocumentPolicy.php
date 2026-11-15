<?php

namespace App\Policies;

use App\Models\ShipmentDocument;
use App\Models\User;

class ShipmentDocumentPolicy
{
    public function view(User $user, ShipmentDocument $doc): bool
    {
        return $user->isStaff() || $doc->shipment->customer_id === $user->id;
    }

    public function delete(User $user, ShipmentDocument $doc): bool
    {
        return $user->isStaff();
    }
}
