<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreRotation extends Model
{
    use BranchScoped;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'tyre_id',
        'vehicle_id',
        'rotation_date',
        'previous_position',
        'new_position',
        'reason',
        'cost',
        'km_at_rotation',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rotation_date' => 'date',
            'cost' => 'decimal:2',
            'km_at_rotation' => 'decimal:2',
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
