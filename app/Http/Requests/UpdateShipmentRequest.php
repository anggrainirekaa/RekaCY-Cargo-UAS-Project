<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

/** PUT/PATCH use the same full rule set (full-resource update). */
class UpdateShipmentRequest extends StoreShipmentRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('shipment'));
    }
}
