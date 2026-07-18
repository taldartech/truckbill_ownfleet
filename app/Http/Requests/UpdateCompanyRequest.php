<?php

namespace App\Http\Requests;

use App\Enums\GstRegistrationType;
use App\Enums\PayrollPayDaysMode;
use App\Enums\TaxLiability;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesCompanyIdentity;
use App\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
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
        /** @var Tenant|null $company */
        $company = $this->route('company');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'gst_number' => $this->companyGstNumberRules($company?->id),
            'pan_number' => $this->companyPanNumberRules($company?->id),
            'registration_type' => ['nullable', Rule::enum(GstRegistrationType::class)],
            'tax_liability' => ['nullable', Rule::enum(TaxLiability::class)],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_person' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number' => $this->companyContactNumberRules($company?->id),
            'timezone' => ['nullable', 'string', 'max:64'],
            'payroll_pay_days_mode' => ['sometimes', Rule::enum(PayrollPayDaysMode::class)],
            'is_blocked' => ['sometimes', 'boolean'],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:100'],
            'remove_logo' => ['sometimes', 'boolean'],
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
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeCompanyIdentityFields();
        $this->normalizeContactNumberInput();
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Tenant|null $company */
        $company = $this->route('company');

        $this->validateCompanyRetainsPanAndContact($validator, $company);
    }
}
