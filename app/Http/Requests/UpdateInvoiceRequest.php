<?php

namespace App\Http\Requests;

use App\Invoice;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && $this->user()?->can('updateCharges', $invoice) === true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'custom_items' => ['present', 'array'],
            'custom_items.*.description' => ['required', 'string', 'max:255'],
            'custom_items.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
