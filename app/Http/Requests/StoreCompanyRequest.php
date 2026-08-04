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
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:100'],
            'document_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
            'company_logo.image' => 'The logo must be an image file.',
            'company_logo.mimes' => 'The logo must be a JPG, PNG, or WEBP image.',
            'company_logo.max' => 'The logo may not be greater than 100 KB.',
            'document_color.regex' => 'Document color must be a valid hex color (e.g. #0B2A55).',
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

        if (! $this->filled('document_color') || $this->input('document_color') === '') {
            $this->merge([
                'document_color' => null,
            ]);
        } elseif (is_string($this->input('document_color'))) {
            $this->merge([
                'document_color' => strtoupper($this->input('document_color')),
            ]);
        }
    }
}
