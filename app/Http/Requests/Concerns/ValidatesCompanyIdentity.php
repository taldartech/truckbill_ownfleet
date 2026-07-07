<?php

namespace App\Http\Requests\Concerns;

use App\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ValidatesCompanyIdentity
{
    protected function normalizeCompanyIdentityFields(): void
    {
        foreach (['gst_number', 'pan_number'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || trim((string) $value) === '') {
                $this->merge([$field => null]);

                continue;
            }

            $this->merge([$field => strtoupper(trim((string) $value))]);
        }

        foreach (['gst_scheme', 'gst_type'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || trim((string) $value) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyGstNumberRules(?string $ignoreTenantId = null): array
    {
        return [
            'nullable',
            'string',
            'max:50',
            'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i',
            $this->companyIdentityUniqueRule('gst_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyPanNumberRules(?string $ignoreTenantId = null, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
            $this->companyIdentityUniqueRule('pan_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyContactNumberRules(?string $ignoreTenantId = null, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            ...$this->tenDigitPhoneRules(),
            $this->companyIdentityUniqueRule('contact_number', $ignoreTenantId),
        ];
    }

    protected function validateCompanyRetainsPanAndContact(Validator $validator, ?Tenant $company = null): void
    {
        $validator->after(function (Validator $validator) use ($company): void {
            if (! $this->requestTouchesCompanyForm()) {
                return;
            }

            $pan = $this->has('pan_number')
                ? $this->input('pan_number')
                : $company?->pan_number;

            if ($pan === null || trim((string) $pan) === '') {
                $validator->errors()->add('pan_number', 'PAN number is required.');
            }

            $contact = $this->has('contact_number')
                ? $this->input('contact_number')
                : $company?->contact_number;

            if ($contact === null || trim((string) $contact) === '') {
                $validator->errors()->add('contact_number', 'Contact number is required.');
            }

            $contactPerson = $this->has('contact_person')
                ? $this->input('contact_person')
                : $company?->contact_person;

            if ($contactPerson === null || trim((string) $contactPerson) === '') {
                $validator->errors()->add('contact_person', 'Contact person is required.');
            }
        });
    }

    private function requestTouchesCompanyForm(): bool
    {
        foreach (['name', 'pan_number', 'contact_number', 'contact_person', 'gst_number'] as $field) {
            if ($this->has($field)) {
                return true;
            }
        }

        return false;
    }

    private function companyIdentityUniqueRule(string $column, ?string $ignoreTenantId = null): Unique
    {
        $rule = Rule::unique('tenants', $column);

        if ($ignoreTenantId !== null) {
            $rule->ignore($ignoreTenantId);
        }

        return $rule;
    }

    /**
     * @return array<string, string>
     */
    protected function companyIdentityMessages(): array
    {
        return [
            'pan_number.required' => 'PAN number is required.',
            'contact_number.required' => 'Contact number is required.',
            'contact_person.required' => 'Contact person is required.',
            'gst_number.regex' => 'Please enter a valid GST number (e.g. 27AAAAA0000A1Z5).',
            'gst_number.unique' => 'This GST number is already registered for another company.',
            'pan_number.unique' => 'This PAN number is already registered for another company.',
            'contact_number.unique' => 'This contact number is already registered for another company.',
        ];
    }
}
