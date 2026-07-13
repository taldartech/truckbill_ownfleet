<?php

namespace App\Http\Requests;

use App\Enums\GstRegistrationType;
use App\Enums\PayrollPayDaysMode;
use App\Enums\TaxLiability;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesCompanyIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    use NormalizesContactNumber;
    use ValidatesCompanyIdentity;

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
            'gst_number' => $this->companyGstNumberRules(),
            'pan_number' => $this->companyPanNumberRules(required: true),
            'registration_type' => ['nullable', Rule::enum(GstRegistrationType::class)],
            'tax_liability' => ['nullable', Rule::enum(TaxLiability::class)],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_number' => $this->companyContactNumberRules(required: true),
            'timezone' => ['nullable', 'string', 'max:64'],
            'payroll_pay_days_mode' => ['nullable', Rule::enum(PayrollPayDaysMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->companyIdentityMessages(),
            ...$this->contactNumberMessages(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCompanyIdentityFields();
        $this->normalizeContactNumberInput();

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
