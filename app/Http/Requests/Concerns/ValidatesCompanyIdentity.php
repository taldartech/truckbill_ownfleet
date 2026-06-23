<?php

namespace App\Http\Requests\Concerns;

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
            $this->companyIdentityUniqueRule('gst_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyPanNumberRules(?string $ignoreTenantId = null): array
    {
        return [
            'nullable',
            'string',
            'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
            $this->companyIdentityUniqueRule('pan_number', $ignoreTenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function companyContactNumberRules(?string $ignoreTenantId = null): array
    {
        return [
            ...$this->contactNumberRules(),
            $this->companyIdentityUniqueRule('contact_number', $ignoreTenantId),
        ];
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
            'gst_number.unique' => 'This GST number is already registered for another company.',
            'pan_number.unique' => 'This PAN number is already registered for another company.',
            'contact_number.unique' => 'This contact number is already registered for another company.',
        ];
    }
}
