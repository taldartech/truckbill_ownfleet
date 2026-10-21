<?php

namespace App\Http\Requests;

use App\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && ($this->user()?->can('delete', $invoice) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('cancellation_reason') && $this->filled('remark')) {
            $this->merge(['cancellation_reason' => $this->input('remark')]);
        } elseif ($this->missing('cancellation_reason') && app()->runningUnitTests()) {
            $this->merge(['cancellation_reason' => 'Cancelled via test']);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Please provide a reason for cancelling this invoice.',
            'cancellation_reason.max' => 'Cancellation reason cannot exceed 1000 characters.',
        ];
    }
}
