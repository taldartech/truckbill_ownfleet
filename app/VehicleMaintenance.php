<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleMaintenance extends Model
{
    use BranchScoped;
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vehicle_id',
        'service_date',
        'odometer_km',
        'maintenance_service_type_id',
        'service_provider',
        'cost_amount',
        'next_service_due_km',
        'next_service_due_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'odometer_km' => 'decimal:2',
            'cost_amount' => 'decimal:2',
            'next_service_due_km' => 'decimal:2',
            'next_service_due_date' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function maintenanceServiceType(): BelongsTo
    {
        return $this->belongsTo(MaintenanceServiceType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
