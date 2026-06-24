<?php

namespace App\Http\Requests\Concerns;

use App\Tenant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ValidatesCompanyIdentity
{
    /**
     * @var list<string>
     */
    private const COMPANY_IDENTITY_FIELDS = ['gst_number', 'pan_number', 'contact_number'];
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
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyGstNumberRules(?string $ignoreTenantId = null, bool $requireAtLeastOneIdentity = true): array
    {
        return [
            ...$this->companyAtLeastOneIdentityRules('gst_number', $requireAtLeastOneIdentity),
            'nullable',
            'string',
            'max:50',
            $this->companyIdentityUniqueRule('gst_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyPanNumberRules(?string $ignoreTenantId = null, bool $requireAtLeastOneIdentity = true): array
    {
        return [
            ...$this->companyAtLeastOneIdentityRules('pan_number', $requireAtLeastOneIdentity),
            'nullable',
            'string',
            'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
            $this->companyIdentityUniqueRule('pan_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyContactNumberRules(?string $ignoreTenantId = null, bool $requireAtLeastOneIdentity = true): array
    {
        return [
            ...$this->companyAtLeastOneIdentityRules('contact_number', $requireAtLeastOneIdentity),
            ...$this->contactNumberRules(),
            $this->companyIdentityUniqueRule('contact_number', $ignoreTenantId),
        ];
    }

    protected function validateCompanyRetainsIdentity(Validator $validator, ?Tenant $company = null): void
    {
        $validator->after(function (Validator $validator) use ($company): void {
            if (! $this->requestTouchesCompanyIdentity()) {
                return;
            }

            if ($this->companyHasAtLeastOneIdentity($company)) {
                return;
            }

            foreach (self::COMPANY_IDENTITY_FIELDS as $field) {
                $validator->errors()->add($field, $this->companyAtLeastOneIdentityMessage());
            }
        });
    }

    private function requestTouchesCompanyIdentity(): bool
    {
        foreach (self::COMPANY_IDENTITY_FIELDS as $field) {
            if ($this->has($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    private function companyAtLeastOneIdentityRules(string $field, bool $requireAtLeastOneIdentity): array
    {
        if (! $requireAtLeastOneIdentity) {
            return [];
        }

        $otherFields = array_values(array_filter(
            self::COMPANY_IDENTITY_FIELDS,
            static fn (string $candidate): bool => $candidate !== $field,
        ));

        return ['required_without_all:'.implode(',', $otherFields)];
    }

    private function companyHasAtLeastOneIdentity(?Tenant $company = null): bool
    {
        foreach (self::COMPANY_IDENTITY_FIELDS as $field) {
            $value = $this->has($field)
                ? $this->input($field)
                : $company?->{$field};

            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function companyAtLeastOneIdentityMessage(): string
    {
        return 'Provide at least one of GST number, PAN number, or contact number.';
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
        $atLeastOneMessage = $this->companyAtLeastOneIdentityMessage();

        return [
            'gst_number.required_without_all' => $atLeastOneMessage,
            'pan_number.required_without_all' => $atLeastOneMessage,
            'contact_number.required_without_all' => $atLeastOneMessage,
            'gst_number.unique' => 'This GST number is already registered for another company.',
            'pan_number.unique' => 'This PAN number is already registered for another company.',
            'contact_number.unique' => 'This contact number is already registered for another company.',
        ];
    }
}
