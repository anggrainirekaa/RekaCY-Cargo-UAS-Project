<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('recordTracking', $this->route('shipment'));
    }

    public function rules(): array
    {
        $isPod = $this->input('type') === DocumentType::Pod->value;

        return [
            'type' => ['required', Rule::enum(DocumentType::class)],
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'recipient_name' => [$isPod ? 'required' : 'nullable', 'string', 'max:120'],
            'received_at' => [$isPod ? 'required' : 'nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['file.mimes' => 'Only PDF, JPG or PNG files are accepted.', 'file.max' => 'The file may not be larger than 5 MB.'];
    }
}
