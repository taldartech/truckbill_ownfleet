<?php

namespace App\Support;

use App\Branch;
use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
use App\Tenant;

class GstSettingsResolver
{
    /**
     * @return array{
     *     inherit_company_gst: bool,
     *     registration_type: ?GstRegistrationType,
     *     tax_liability: ?TaxLiability,
     *     gst_number: ?string,
     *     gst_rate: ?float,
     *     state_id: ?int,
     *     state_code: ?string,
     *     source: 'company'|'branch'
     * }
     */
    public function forBranch(Branch $branch, ?Tenant $company = null): array
    {
        $company ??= $branch->relationLoaded('company')
            ? $branch->company
            : $branch->company()->first();

        $inherit = (bool) ($branch->inherit_company_gst ?? true);

        if ($inherit || $company === null) {
            return $this->fromCompany($company, inherit: true);
        }

        $registrationType = $branch->registration_type instanceof GstRegistrationType
            ? $branch->registration_type
            : GstRegistrationType::tryFrom((string) ($branch->registration_type ?? ''));

        $taxLiability = $branch->tax_liability instanceof TaxLiability
            ? $branch->tax_liability
            : TaxLiability::fromLegacy($branch->tax_liability !== null ? (string) $branch->tax_liability : null);

        if ($registrationType !== GstRegistrationType::Regular) {
            $taxLiability = null;
        }

        $state = $branch->relationLoaded('state') ? $branch->state : $branch->state()->first();

        return [
            'inherit_company_gst' => false,
            'registration_type' => $registrationType,
            'tax_liability' => $taxLiability,
            'gst_number' => $branch->gst_number,
            'gst_rate' => $this->normalizeGstRate($branch->gst_rate, $registrationType),
            'state_id' => $branch->state_id !== null ? (int) $branch->state_id : null,
            'state_code' => $state?->code,
            'source' => 'branch',
        ];
    }

    /**
     * @return array{
     *     inherit_company_gst: bool,
     *     registration_type: ?GstRegistrationType,
     *     tax_liability: ?TaxLiability,
     *     gst_number: ?string,
     *     gst_rate: ?float,
     *     state_id: ?int,
     *     state_code: ?string,
     *     source: 'company'|'branch'
     * }
     */
    public function fromCompany(?Tenant $company, bool $inherit = false): array
    {
        if ($company === null) {
            return [
                'inherit_company_gst' => $inherit,
                'registration_type' => null,
                'tax_liability' => null,
                'gst_number' => null,
                'gst_rate' => null,
                'state_id' => null,
                'state_code' => null,
                'source' => 'company',
            ];
        }

        $registrationType = $company->registration_type instanceof GstRegistrationType
            ? $company->registration_type
            : GstRegistrationType::tryFrom((string) ($company->registration_type ?? ''));

        $taxLiability = $company->tax_liability instanceof TaxLiability
            ? $company->tax_liability
            : TaxLiability::fromLegacy($company->tax_liability !== null ? (string) $company->tax_liability : null);

        if ($registrationType !== GstRegistrationType::Regular) {
            $taxLiability = null;
        }

        $state = $company->relationLoaded('state') ? $company->state : $company->state()->first();

        return [
            'inherit_company_gst' => $inherit,
            'registration_type' => $registrationType,
            'tax_liability' => $taxLiability ?? ($registrationType === GstRegistrationType::Regular ? TaxLiability::ForwardCharge : null),
            'gst_number' => $company->gst_number,
            'gst_rate' => $this->normalizeGstRate($company->gst_rate, $registrationType),
            'state_id' => $company->state_id !== null ? (int) $company->state_id : null,
            'state_code' => $state?->code,
            'source' => 'company',
        ];
    }

    private function normalizeGstRate(mixed $rate, ?GstRegistrationType $registrationType): ?float
    {
        if ($registrationType !== GstRegistrationType::Regular) {
            return null;
        }

        if ($rate === null || $rate === '') {
            return null;
        }

        return round((float) $rate, 3);
    }
}
