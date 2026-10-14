<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatteryInstallation extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'battery_id',
        'vehicle_id',
        'slot_name',
        'installed_at',
        'removed_at',
        'km_at_installation',
        'km_at_removal',
        'running_km',
        'removal_reason',
        'notes',
        'installed_by',
        'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'removed_at' => 'date',
            'km_at_installation' => 'decimal:2',
            'km_at_removal' => 'decimal:2',
            'running_km' => 'decimal:2',
        ];
    }

    public function battery(): BelongsTo
    {
        return $this->belongsTo(Battery::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
