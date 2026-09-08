<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => [
                'required',
                Rule::in(['cash', 'pos', 'online', 'card_to_card']),
            ],
            'paid_at' => ['nullable', 'date'],
            'notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
