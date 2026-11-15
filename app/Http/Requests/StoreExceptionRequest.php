<?php

namespace App\Http\Requests;

use App\Enums\ExceptionType;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('manageExceptions', $this->route('shipment'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExceptionType::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
