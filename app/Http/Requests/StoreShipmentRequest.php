<?php

namespace App\Http\Requests;

use App\Enums\ShipmentPriority;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('create', Shipment::class);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'customer')],
            'carrier_id' => ['required', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'origin_id' => ['required', 'integer', 'exists:locations,id'],
            'destination_id' => ['required', 'integer', 'exists:locations,id', 'different:origin_id'],
            'priority' => ['nullable', Rule::enum(ShipmentPriority::class)],
            'cargo_type' => ['required', 'string', 'max:100'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:5000000'],
            'volume_cbm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'expected_departure_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:expected_departure_at'],
            'estimated_arrival_at' => ['nullable', 'date', 'after:expected_departure_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'destination_id.different' => 'Origin and destination must be different locations.',
            'expected_arrival_at.after' => 'Expected arrival must be after the expected departure.',
        ];
    }
}
