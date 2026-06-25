<?php

namespace App\Http\Requests;

use App\Enums\PayrollPayDaysMode;
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
            'gst_scheme' => ['nullable', 'string', 'max:50'],
            'gst_type' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => $this->companyContactNumberRules($company?->id),
            'timezone' => ['nullable', 'string', 'max:64'],
            'payroll_pay_days_mode' => ['sometimes', Rule::enum(PayrollPayDaysMode::class)],
            'is_blocked' => ['sometimes', 'boolean'],
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
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Tenant|null $company */
        $company = $this->route('company');

        $this->validateCompanyRetainsPanAndContact($validator, $company);
    }
}
