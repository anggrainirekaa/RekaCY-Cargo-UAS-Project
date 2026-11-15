<?php

namespace App\Http\Requests;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['GENERAL', 'DELAY', 'DAMAGE', 'DOCUMENT', 'BILLING'])],
            'priority' => ['required', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            // customers may only reference their own shipments
            'shipment_id' => ['nullable', 'integer', Rule::exists('shipments', 'id')->where(fn ($q) => $user->isCustomer() ? $q->where('customer_id', $user->id) : $q)],
            'body' => ['required', 'string', 'max:4000'],
        ];
    }
}
