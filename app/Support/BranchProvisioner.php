<?php

namespace App\Support;

use App\Branch;
use App\Tenant;

class BranchProvisioner
{
    public static function ensureDefaultBranch(Tenant $tenant): Branch
    {
        $branch = Branch::query()
            ->where('company_id', $tenant->id)
            ->orderByDesc('head_office')
            ->orderBy('id')
            ->first();

        if ($branch !== null) {
            return $branch;
        }

        return Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => $tenant->name,
            'branch_address' => $tenant->address ?? '',
            'branch_contact_number' => self::defaultContactNumber($tenant),
            'head_office' => true,
        ]);
    }

    private static function defaultContactNumber(Tenant $tenant): string
    {
        $digits = preg_replace('/\D/', '', (string) ($tenant->contact_number ?? ''));

        if (strlen($digits) === 10) {
            return $digits;
        }

        $suffix = str_pad((string) (abs(crc32((string) $tenant->id)) % 100000000), 8, '0', STR_PAD_LEFT);

        return '9'.$suffix;
    }
}
