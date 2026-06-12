<?php

namespace App\Http\Requests;

use App\Enums\PayrollPayDaysMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'gst_scheme' => ['nullable', 'string', 'max:50'],
            'gst_type' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'payroll_pay_days_mode' => ['nullable', Rule::enum(PayrollPayDaysMode::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (
            ! $this->filled('payroll_pay_days_mode')
            || $this->input('payroll_pay_days_mode') === ''
        ) {
            $this->merge([
                'payroll_pay_days_mode' => PayrollPayDaysMode::Calendar->value,
            ]);
        }
    }
}
