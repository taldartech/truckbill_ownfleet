<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'tds_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_reason' => [
                Rule::requiredIf(fn () => (float) $this->input('deduction_amount', 0) > 0),
                'nullable',
                'string',
                'max:255',
            ],
            'paid_at' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount cannot be negative.',
            'tds_amount.min' => 'TDS amount cannot be negative.',
            'deduction_amount.min' => 'Deduction amount cannot be negative.',
            'deduction_reason.required' => 'Please provide a reason for the deduction / discount (e.g. Shortage, Discount, Damage).',
            'paid_at.required' => 'Payment date is required.',
        ];
    }
}
