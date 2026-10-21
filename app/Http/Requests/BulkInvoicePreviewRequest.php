<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkInvoicePreviewRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'trip_ids' => ['required', 'array', 'min:1'],
            'trip_ids.*' => ['required', 'uuid', 'distinct', 'exists:trips,id'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'particulars_mode' => ['nullable', 'string', 'in:combined,separate'],
            'invoice_sequence' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'custom_items' => ['nullable', 'array'],
            'custom_items.*.description' => ['required', 'string', 'max:255'],
            'custom_items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Select a customer.',
            'trip_ids.required' => 'Select at least one trip.',
            'trip_ids.min' => 'Select at least one trip.',
            'custom_items.*.description.required' => 'Each custom charge needs a description.',
            'custom_items.*.amount.required' => 'Each custom charge needs an amount.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $invoiceDate = $this->input('invoice_date');
            $dueDate = $this->input('due_date');

            if (
                is_string($invoiceDate) && trim($invoiceDate) !== ''
                && is_string($dueDate) && trim($dueDate) !== ''
                && $dueDate < $invoiceDate
            ) {
                $validator->errors()->add(
                    'due_date',
                    'Due date cannot be earlier than the invoice date.',
                );
            }
        });
    }
}
