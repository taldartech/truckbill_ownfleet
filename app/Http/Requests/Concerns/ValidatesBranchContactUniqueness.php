<?php

namespace App\Http\Requests\Concerns;

use App\Branch;
use App\Tenant;
use Illuminate\Validation\Validator;

trait ValidatesBranchContactUniqueness
{
    protected function validateBranchContactNumberUniqueness(
        Validator $validator,
        string $companyId,
        ?string $contactNumber,
        ?int $ignoreBranchId = null,
    ): void {
        if ($contactNumber === null || $contactNumber === '') {
            return;
        }

        $company = Tenant::query()->find($companyId);
        $companyContact = self::normalizePhoneDigits($company?->contact_number);

        if ($companyContact !== null && $contactNumber === $companyContact) {
            return;
        }

        $belongsToAnotherCompany = Tenant::query()
            ->whereKeyNot($companyId)
            ->whereNotNull('contact_number')
            ->get(['contact_number'])
            ->contains(
                fn (Tenant $tenant): bool => self::normalizePhoneDigits($tenant->contact_number) === $contactNumber
            );

        if ($belongsToAnotherCompany) {
            $validator->errors()->add(
                'branch_contact_number',
                'This number belongs to another company.',
            );

            return;
        }

        $branchQuery = Branch::query()->where('branch_contact_number', $contactNumber);

        if ($ignoreBranchId !== null) {
            $branchQuery->whereKeyNot($ignoreBranchId);
        }

        if ($branchQuery->exists()) {
            $validator->errors()->add(
                'branch_contact_number',
                'This number is already used by another branch.',
            );
        }
    }

    private static function normalizePhoneDigits(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
