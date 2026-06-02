<?php

namespace App\Http\Requests;

use App\Driver;
use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
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
            'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/', 'max:20'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiry_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'aadhaar_number' => ['nullable', 'string', 'regex:/^[0-9]{12}$/'],
            'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
            'joining_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'max:20'],
            'daily_wage' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid 10-digit mobile number.',
            'emergency_contact_phone.regex' => 'Please enter a valid 10-digit emergency contact number.',
            'aadhaar_number.regex' => 'Aadhaar number must be 12 digits.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
        ];
    }
}
