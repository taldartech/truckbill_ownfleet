<?php

namespace App;

use App\Concerns\TenantScoped;
use App\Concerns\UsesUuid;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;
    use TenantScoped;
    use UsesUuid;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'substitute_driver_id',
        'trip_number',
        'customer_id',
        'customer_name',
        'broker_id',
        'broker_name',
        'start_station_id',
        'status',
        'start_at',
        'end_at',
        'cancelled_at',
        'cancellation_reason',
        'actual_arrival_at',
        'total_distance_km',
        'loaded_distance_km',
        'half_loaded_distance_km',
        'empty_distance_km',
        'total_expense_amount',
        'total_freight_amount',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'actual_arrival_at' => 'datetime',
            'total_distance_km' => 'decimal:2',
            'loaded_distance_km' => 'decimal:2',
            'half_loaded_distance_km' => 'decimal:2',
            'empty_distance_km' => 'decimal:2',
            'total_expense_amount' => 'decimal:2',
            'total_freight_amount' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function substituteDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'substitute_driver_id')->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Broker::class);
    }

    public function startStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'start_station_id');
    }

    public function routeLegs(): HasMany
    {
        return $this->hasMany(TripRouteLeg::class)->orderBy('sequence_no');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TripExpense::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TripStatusHistory::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function profitAmount(): float
    {
        return (float) $this->total_freight_amount - (float) $this->total_expense_amount;
    }
}
