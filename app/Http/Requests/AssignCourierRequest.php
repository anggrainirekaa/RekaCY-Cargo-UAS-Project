<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignCourierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('operate');
    }

    public function rules(): array
    {
        return [
            'courier_id' => ['required', 'integer', Rule::exists('couriers', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
