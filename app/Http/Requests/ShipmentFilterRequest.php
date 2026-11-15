<?php

namespace App\Http\Requests;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'carrier_id' => ['nullable', 'integer', 'exists:carriers,id'],
            'origin_id' => ['nullable', 'integer', 'exists:locations,id'],
            'destination_id' => ['nullable', 'integer', 'exists:locations,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'delay' => ['nullable', Rule::in(['delayed', 'on_time'])],
            'sort' => ['nullable', Rule::in(Shipment::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
