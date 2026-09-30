<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatteryInspection extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'battery_id',
        'vehicle_id',
        'inspected_at',
        'voltage_reading',
        'specific_gravity',
        'condition',
        'action_taken',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'voltage_reading' => 'decimal:2',
            'specific_gravity' => 'decimal:3',
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
