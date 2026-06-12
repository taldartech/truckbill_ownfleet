<?php

namespace App\Http\Requests\Concerns;

use App\Support\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ValidatesPartyIdentity
{
    protected function normalizePartyIdentityFields(): void
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
    protected function partyGstNumberRules(string $table, ?int $ignoreId = null, ?string $tenantId = null): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            $this->partyIdentityUniqueRule($table, 'gst_number', $ignoreId, $tenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function partyPanNumberRules(string $table, ?int $ignoreId = null, ?string $tenantId = null): array
    {
        return [
            'nullable',
            'string',
            'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
            $this->partyIdentityUniqueRule($table, 'pan_number', $ignoreId, $tenantId),
        ];
    }

    /**
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    protected function partyContactNumberRules(string $table, ?int $ignoreId = null, ?string $tenantId = null): array
    {
        return [
            ...$this->contactNumberRules(),
            $this->partyIdentityUniqueRule($table, 'contact_number', $ignoreId, $tenantId),
        ];
    }

    private function partyIdentityUniqueRule(
        string $table,
        string $column,
        ?int $ignoreId = null,
        ?string $tenantId = null,
    ): Unique {
        $tenantId ??= CurrentTenant::id();

        $rule = Rule::unique($table, $column)
            ->where(function (Builder $query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at');
            });

        if ($ignoreId !== null) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    /**
     * @return array<string, string>
     */
    protected function partyIdentityMessages(): array
    {
        return [
            'gst_number.unique' => 'This GST number is already registered for another customer in your company.',
            'pan_number.unique' => 'This PAN number is already registered for another customer in your company.',
            'contact_number.unique' => 'This contact number is already registered for another customer in your company.',
        ];
    }
}
