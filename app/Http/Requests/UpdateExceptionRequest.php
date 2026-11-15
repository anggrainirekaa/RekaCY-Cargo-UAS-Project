<?php

namespace App\Http\Requests;

use App\Enums\ExceptionStatus;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('shipmentException'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(ExceptionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution_note' => ['nullable', 'string', 'max:1000', 'required_if:status,RESOLVED'],
        ];
    }
}
