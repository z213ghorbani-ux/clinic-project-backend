<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => [
                'required',
                'integer',
                'exists:appointments,id',
                'unique:invoices,appointment_id', // هر نوبت فقط یک فاکتور
            ],
            'amount' => ['required', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'lte:amount'],
            'status' => ['nullable', Rule::in(['unpaid', 'paid', 'cancelled'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'pos', 'online', 'card_to_card'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
