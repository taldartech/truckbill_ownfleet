<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'trip_number',
        'status',
        'start_at',
        'end_at',
        'total_distance_km',
        'total_expense_amount',
        'total_freight_amount',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'total_distance_km' => 'decimal:2',
            'total_expense_amount' => 'decimal:2',
            'total_freight_amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function routeLegs(): HasMany
    {
        return $this->hasMany(TripRouteLeg::class);
    }
}
