<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TyreDamageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreDamage extends Model
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
        'damage_type',
        'occurred_at',
        'km_at_occurrence',
        'description',
        'repair_cost',
        'is_repaired',
    ];

    protected function casts(): array
    {
        return [
            'damage_type' => TyreDamageType::class,
            'occurred_at' => 'date',
            'km_at_occurrence' => 'decimal:2',
            'repair_cost' => 'decimal:2',
            'is_repaired' => 'boolean',
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
