<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Replaces the per-request tenant/branch scopes with an explicit set of companies,
 * so a Super Admin can aggregate data across every company they own.
 */
class SuperAdminPortfolioScope
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $tenantIds
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $tenantIds): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->withoutGlobalScopes(['tenant', 'branch'])
            ->whereIn("{$table}.tenant_id", $tenantIds);
    }
}
