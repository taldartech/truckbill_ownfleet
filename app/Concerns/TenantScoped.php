<?php

namespace App\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::creating(function ($model): void {
            if (empty($model->tenant_id) && TenantContext::tenantId() !== null) {
                $model->tenant_id = TenantContext::tenantId();
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = TenantContext::tenantId();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });
    }
}
