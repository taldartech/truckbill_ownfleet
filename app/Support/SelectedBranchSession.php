<?php

namespace App\Support;

class SelectedBranchSession
{
    public const BRANCH_KEY = 'super_admin_branch_id';

    public static function id(): ?int
    {
        $branchId = session(self::BRANCH_KEY);

        if ($branchId === null) {
            return null;
        }

        return (int) $branchId;
    }

    public static function set(?int $branchId): void
    {
        if ($branchId === null) {
            self::forget();

            return;
        }

        session([self::BRANCH_KEY => $branchId]);
    }

    public static function forget(): void
    {
        session()->forget(self::BRANCH_KEY);
    }
}
