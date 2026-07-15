<?php

namespace App\Http\Requests\Concerns;

use App\Support\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait ValidatesDriverIdentity
{
    protected function normalizeDriverIdentityFields(): void
    {
        $nullableFields = ['license_number', 'aadhaar_number', 'pan_number'];

        foreach ($nullableFields as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || trim((string) $value) === '') {
                $this->merge([$field => null]);

                continue;
            }

            if ($field === 'license_number') {
                $cleaned = strtoupper(str_replace([' ', '-'], '', trim((string) $value)));
                $this->merge([$field => $cleaned]);
            } elseif ($field === 'pan_number') {
                $this->merge([$field => strtoupper(trim((string) $value))]);
            }
        }
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function driverPhoneRules(bool $required = true, ?string $ignoreDriverId = null): array
    {
        $presence = $required ? ['required'] : ['sometimes', 'required'];

        $unique = Rule::unique('drivers', 'phone')
            ->where(fn (Builder $query) => $this->scopedActiveDrivers($query));

        if ($ignoreDriverId !== null) {
            $unique->ignore($ignoreDriverId);
        }

        return [
            ...$presence,
            'string',
            'regex:/^[0-9]{10}$/',
            'max:20',
            $unique,
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function driverLicenseNumberRules(?string $ignoreDriverId = null, bool $required = true): array
    {
        $presence = $required ? ['required'] : ['sometimes', 'required'];

        return [
            ...$presence,
            'string',
            'min:13',
            'max:18',
            'regex:/^[A-Z]{2}[A-Z0-9]+$/',
            $this->driverIdentityUniqueRule('license_number', $ignoreDriverId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function driverAadhaarNumberRules(?string $ignoreDriverId = null): array
    {
        return [
            'nullable',
            'string',
            'regex:/^[0-9]{12}$/',
            $this->driverIdentityUniqueRule('aadhaar_number', $ignoreDriverId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function driverPanNumberRules(?string $ignoreDriverId = null): array
    {
        return [
            'nullable',
            'string',
            'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
            $this->driverIdentityUniqueRule('pan_number', $ignoreDriverId),
        ];
    }

    private function driverIdentityUniqueRule(string $column, ?string $ignoreDriverId = null): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('drivers', $column)
            ->where(fn (Builder $query) => $this->scopedActiveDrivers($query));

        if ($ignoreDriverId !== null) {
            $rule->ignore($ignoreDriverId);
        }

        return $rule;
    }

    private function scopedActiveDrivers(Builder $query): void
    {
        $query->where('tenant_id', CurrentTenant::requireId())
            ->whereNull('deleted_at');
    }

    /**
     * @return array<string, string>
     */
    protected function driverIdentityMessages(): array
    {
        return [
            'phone.unique' => 'This mobile number is already registered for another driver in your company.',
            'license_number.unique' => 'This license number is already registered for another driver in your company.',
            'aadhaar_number.unique' => 'This Aadhaar number is already registered for another driver in your company.',
            'pan_number.unique' => 'This PAN number is already registered for another driver in your company.',
        ];
    }
}
