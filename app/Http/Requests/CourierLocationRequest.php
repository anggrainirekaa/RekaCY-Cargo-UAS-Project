<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourierLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCourier() === true && $this->user()->courier !== null;
    }

    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'recorded_at' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(['device', 'demo'])],
        ];
    }
}
