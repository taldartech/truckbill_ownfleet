<?php

namespace App\Http\Requests;

use App\Driver;
use App\Http\Requests\Concerns\ValidatesDriverIdentity;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    use ValidatesDriverIdentity;

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->input('phone') !== null) {
            $this->merge([
                'phone' => (string) $this->input('phone'),
            ]);
        }

        if ($this->has('emergency_contact_phone') && $this->input('emergency_contact_phone') !== null) {
            $this->merge([
                'emergency_contact_phone' => (string) $this->input('emergency_contact_phone'),
            ]);
        }

        if ($this->has('daily_wage') && $this->input('daily_wage') !== null) {
            $this->merge([
                'daily_wage' => number_format((float) $this->input('daily_wage'), 2, '.', ''),
            ]);
        }

        $this->normalizeDriverIdentityFields();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Driver::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => $this->driverPhoneRules(),
            'license_number' => $this->driverLicenseNumberRules(),
            'license_expiry_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'aadhaar_number' => $this->driverAadhaarNumberRules(),
            'pan_number' => $this->driverPanNumberRules(),
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'joining_date' => ['required', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'max:20'],
            'daily_wage' => ['required', 'numeric', 'gt:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->driverIdentityMessages(),
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'emergency_contact_phone.regex' => 'Please enter a valid 10-digit emergency contact number.',
            'aadhaar_number.regex' => 'Aadhaar number must be 12 digits.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
            'joining_date.required' => 'Joining date is required.',
            'daily_wage.required' => 'Monthly payment base is required.',
            'daily_wage.gt' => 'Monthly payment base must be greater than 0.',
            'license_number.required' => 'Driving licence number is required.',
            'license_number.regex' => 'Please enter a valid driving licence number.',
        ];
    }
}
