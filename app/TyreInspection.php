<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyreInspection extends Model
{
    use TenantScoped;
    use BranchScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'tyre_id',
        'vehicle_id',
        'inspected_at',
        'tread_depth_mm',
        'pressure_psi',
        'result',
        'next_due_at',
        'notes',
        'inspector_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'next_due_at' => 'date',
            'tread_depth_mm' => 'decimal:2',
            'pressure_psi' => 'decimal:2',
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

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
