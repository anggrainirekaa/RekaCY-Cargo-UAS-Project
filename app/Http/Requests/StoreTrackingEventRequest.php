<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTrackingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('recordTracking', $this->route('shipment'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'occurred_at' => ['nullable', 'date'],
            'estimated_arrival_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500', 'required_without_all:status,location_id,latitude'],
        ];
    }
}
