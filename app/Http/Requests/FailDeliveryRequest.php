<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FailDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCourier() === true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
