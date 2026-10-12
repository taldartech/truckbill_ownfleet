<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatteryReplacement extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vehicle_id',
        'slot_name',
        'old_battery_id',
        'new_battery_id',
        'replaced_at',
        'odometer_km',
        'reason',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'date',
            'odometer_km' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function oldBattery(): BelongsTo
    {
        return $this->belongsTo(Battery::class, 'old_battery_id');
    }

    public function newBattery(): BelongsTo
    {
        return $this->belongsTo(Battery::class, 'new_battery_id');
    }
}
