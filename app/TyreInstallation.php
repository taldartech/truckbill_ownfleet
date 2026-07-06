<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreInstallation extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'tyre_id',
        'vehicle_id',
        'position_code',
        'installed_at',
        'removed_at',
        'km_at_installation',
        'km_at_removal',
        'stint_km',
        'running_km',
        'removal_recommendation',
        'created_by',
        'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'removed_at' => 'date',
            'km_at_installation' => 'decimal:2',
            'km_at_removal' => 'decimal:2',
            'stint_km' => 'decimal:2',
            'running_km' => 'decimal:2',
        ];
    }

    public function tyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
