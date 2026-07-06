<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreReplacement extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vehicle_id',
        'position_code',
        'old_tyre_id',
        'new_tyre_id',
        'replaced_at',
        'reason',
        'km_at_replacement',
        'old_tyre_stint_km',
        'old_tyre_remaining_life_km',
        'recommendation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'date',
            'km_at_replacement' => 'decimal:2',
            'old_tyre_stint_km' => 'decimal:2',
            'old_tyre_remaining_life_km' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function oldTyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class, 'old_tyre_id');
    }

    public function newTyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class, 'new_tyre_id');
    }
}
