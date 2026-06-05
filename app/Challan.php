<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\ChallanPaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Challan extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'challan_number',
        'challan_date',
        'violation_type',
        'amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
            'amount' => 'decimal:2',
            'status' => ChallanPaymentStatus::class,
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
