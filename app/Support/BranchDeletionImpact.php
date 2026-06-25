<?php

namespace App\Support;

use App\Branch;
use App\Driver;
use App\User;
use App\Vehicle;

class BranchDeletionImpact
{
    /**
     * @return array{trucks_count: int, drivers_count: int, users_count: int}
     */
    public static function forBranch(Branch $branch): array
    {
        return [
            'trucks_count' => Vehicle::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->count(),
            'drivers_count' => Driver::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->count(),
            'users_count' => User::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->count(),
        ];
    }
}
