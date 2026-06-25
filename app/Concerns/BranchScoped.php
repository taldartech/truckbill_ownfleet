<?php

namespace App\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

trait BranchScoped
{
    protected static function bootBranchScoped(): void
    {
        static::creating(function ($model): void {
            if (empty($model->branch_id) && BranchContext::branchId() !== null) {
                $model->branch_id = BranchContext::branchId();
            }
        });

        static::addGlobalScope('branch', function (Builder $builder): void {
            $branchId = BranchContext::branchId();

            if ($branchId === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.branch_id', $branchId);
        });
    }
}
